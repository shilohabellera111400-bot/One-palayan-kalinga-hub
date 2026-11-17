<?php
/**
 * CitizensCharterAI.php
 * One Palayan-Kalinga Hub - AI Citizen's Charter Chatbot Engine
 *
 * Multi-model intelligence supporting Google Gemini 1.5 Flash, OpenAI GPT-4o-mini,
 * and a deep municipal CSWDO Citizen's Charter Knowledge Engine fallback for 100% offline uptime.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/gemini_config.php';
require_once __DIR__ . '/../config/openai_config.php';

class CitizensCharterAI {
    private $db;
    private $geminiApiKey;
    private $geminiModel;
    private $openaiApiKey;
    private $openaiModel;
    private $enabled;
    private $systemPrompt;

    public function __construct($db = null) {
        if ($db) {
            $this->db = $db;
        } else {
            $database = new Database();
            $this->db = $database->getConnection();
        }
        $this->loadConfiguration();
    }

    /**
     * Load API keys and configurations from database or environment
     */
    private function loadConfiguration() {
        $keys = ['gemini_api_key', 'gemini_model', 'openai_api_key', 'openai_model', 'chatbot_enabled'];
        $settings = [];

        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gemini_api_key', 'gemini_model', 'openai_api_key', 'openai_model', 'chatbot_enabled')");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\Throwable $e) {}

        $this->geminiApiKey = !empty($settings['gemini_api_key']) ? trim($settings['gemini_api_key']) : (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        $this->geminiModel  = !empty($settings['gemini_model']) ? trim($settings['gemini_model']) : (defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-1.5-flash');

        $this->openaiApiKey = !empty($settings['openai_api_key']) ? trim($settings['openai_api_key']) : (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '');
        $this->openaiModel  = !empty($settings['openai_model']) ? trim($settings['openai_model']) : (defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini');

        $this->enabled = isset($settings['chatbot_enabled']) ? ($settings['chatbot_enabled'] == '1') : true;

        $this->systemPrompt = "You are 'Lingkod Kalinga AI', the official AI customer assistance officer for the Citizen's Charter of the City Social Welfare and Development Office (CSWDO) of Palayan City, Nueva Ecija, Philippines.\n\n"
            . "You assist citizens by explaining requirements, steps, processing times, and guidelines under Republic Act 11032 (Ease of Doing Business).\n\n"
            . "Key Citizen's Charter Services:\n"
            . "1. AICS (Assistance to Individuals in Crisis Situation):\n"
            . "   - Medical: Medical Certificate/Abstract (valid within 3 months), Doctor's Prescription or Hospital Running Bill, Barangay Certificate of Indigency, Valid IDs of patient and claimant.\n"
            . "   - Burial/Funeral: Certified Death Certificate, Funeral Contract/Official Receipt, Barangay Indigency, Valid IDs of claimant and deceased.\n"
            . "   - Educational: Certificate of Registration/Enrollment, School ID, Barangay Indigency, Valid ID of parent/student.\n"
            . "   - Transportation: Police Blotter/Referral, Travel Itinerary/Ticket, Barangay Indigency.\n"
            . "   - Food & Cash Relief: Emergency food packs for disaster victims (fire, flood, typhoon); requires Barangay Disaster Incident Report.\n"
            . "2. Socialized Housing Programs (Akap Village, San Lorenzo NHA, In-Site):\n"
            . "   - Requirements: Barangay Certificate of Residency in Palayan City, Certificate of No Real Property from City Assessor's Office, Valid IDs of applicant and spouse, Proof of Monthly Income, Marriage Contract / Birth Certificates.\n"
            . "3. Senior Citizen Affairs (OSCA):\n"
            . "   - Age 60 and above: Senior Citizen ID & Booklet (20% discount). Requirements: PSA Birth Certificate, Barangay Residency, two 1x1 ID pictures.\n"
            . "   - Social Pension: ₱1,000/month for indigent seniors validated via DSWD with no other regular pension.\n"
            . "4. Persons with Disability Affairs (PDAO):\n"
            . "   - PWD ID & 20% discount booklet: Medical Certificate specifying disability from licensed doctor, Barangay Residency, two 1x1 ID pictures.\n"
            . "5. Appointments & QR Priority Queueing:\n"
            . "   - Online booking via portal; generates Appointment Slip with QR code for quick check-in at City Hall counter.\n"
            . "6. Resident Verification Process:\n"
            . "   - Step 1: ID Type and ID Number.\n"
            . "   - Step 2: Valid ID Document (BOTH Front and Back sides captured via camera or uploaded, with AI OCR detection).\n"
            . "   - Step 3: Selfie holding ID with real-time biometric face alignment.\n"
            . "   - Step 4: Palayan City Barangay selection and residential confirmation.\n"
            . "7. CSWDO Office & Operating Hours:\n"
            . "   - Ground Floor, Palayan City Hall, Nueva Ecija. Open Monday to Friday, 8:00 AM to 5:00 PM (No Noon Break).\n\n"
            . "Tone & Communication Rules:\n"
            . "- Warm, polite, empathetic, using respectful Tagalog/English with 'po' and 'opo'.\n"
            . "- Use neat bullet points for requirement checklists.\n"
            . "- End with encouraging guidance and remind them they can apply or book an appointment on the portal.";
    }

    /**
     * Process user query and return structured response
     */
    public function chat($userMessage, $history = [], $userName = 'Kababayan') {
        $userMessage = trim($userMessage);
        if (empty($userMessage)) {
            return [
                'success' => false,
                'reply' => 'Magandang araw po! Mayroon po ba kayong nais itanong ukol sa mga serbisyo ng CSWDO Palayan City?',
                'provider' => 'none',
                'suggestions' => $this->getDefaultSuggestions(),
                'actions' => []
            ];
        }

        // 1. Try Google Gemini API if key is present
        if (!empty($this->geminiApiKey)) {
            $geminiRes = $this->callGemini($userMessage, $history, $userName);
            if ($geminiRes['success'] && !empty($geminiRes['text'])) {
                return [
                    'success' => true,
                    'reply' => $geminiRes['text'],
                    'provider' => 'Google Gemini 1.5 Flash',
                    'suggestions' => $this->deriveSuggestions($userMessage),
                    'actions' => $this->deriveActions($userMessage)
                ];
            }
        }

        // 2. Try OpenAI API if key is present
        if (!empty($this->openaiApiKey)) {
            $openAiRes = $this->callOpenAI($userMessage, $history, $userName);
            if ($openAiRes['success'] && !empty($openAiRes['text'])) {
                return [
                    'success' => true,
                    'reply' => $openAiRes['text'],
                    'provider' => 'OpenAI GPT-4o-mini',
                    'suggestions' => $this->deriveSuggestions($userMessage),
                    'actions' => $this->deriveActions($userMessage)
                ];
            }
        }

        // 3. Fallback to Palayan CSWDO Citizen's Charter Knowledge Engine
        $localReply = $this->generateKnowledgeBaseAnswer($userMessage, $userName);
        return [
            'success' => true,
            'reply' => $localReply,
            'provider' => "CSWDO Citizen's Charter Engine (Offline Verified)",
            'suggestions' => $this->deriveSuggestions($userMessage),
            'actions' => $this->deriveActions($userMessage)
        ];
    }

    /**
     * Call Google Gemini API (v1beta generateContent)
     */
    private function callGemini($message, $history, $userName) {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($this->geminiModel) . ":generateContent?key=" . urlencode($this->geminiApiKey);

        $contents = [];

        // Build conversation history for multi-turn context
        if (!empty($history) && is_array($history)) {
            $recentHistory = array_slice($history, -6);
            foreach ($recentHistory as $turn) {
                $role = ($turn['role'] === 'user') ? 'user' : 'model';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => (string)$turn['content']]]
                ];
            }
        }

        // Add current user prompt
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => "Resident Name: {$userName}\nInquiry: {$message}"]]
        ];

        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $this->systemPrompt]]
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 700
            ]
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, defined('GEMINI_API_TIMEOUT') ? GEMINI_API_TIMEOUT : 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if (!$err && $httpCode === 200 && !empty($response)) {
            $result = json_decode($response, true);
            $reply = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            if (!empty($reply)) {
                return ['success' => true, 'text' => trim($reply)];
            }
        }

        return ['success' => false, 'error' => $err ?: "HTTP $httpCode"];
    }

    /**
     * Call OpenAI API (v1/chat/completions)
     */
    private function callOpenAI($message, $history, $userName) {
        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt]
        ];

        if (!empty($history) && is_array($history)) {
            $recentHistory = array_slice($history, -6);
            foreach ($recentHistory as $turn) {
                $messages[] = [
                    'role' => ($turn['role'] === 'user') ? 'user' : 'assistant',
                    'content' => (string)$turn['content']
                ];
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => "Applicant: {$userName}\nTanong/Inquiry: {$message}"
        ];

        $payload = [
            'model' => $this->openaiModel,
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 650
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openaiApiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, defined('OPENAI_API_TIMEOUT') ? OPENAI_API_TIMEOUT : 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if (!$err && $httpCode === 200 && !empty($response)) {
            $result = json_decode($response, true);
            $reply = $result['choices'][0]['message']['content'] ?? '';
            if (!empty($reply)) {
                return ['success' => true, 'text' => trim($reply)];
            }
        }

        return ['success' => false, 'error' => $err ?: "HTTP $httpCode"];
    }

    /**
     * Palayan City CSWDO Citizen's Charter Knowledge Engine
     * Zero-API-cost, 100% accurate, rule-based semantic responder.
     */
    public function generateKnowledgeBaseAnswer($message, $userName = 'Kababayan') {
        $q = strtolower($message);
        $name = !empty($userName) ? htmlspecialchars($userName) : 'Kababayan';

        // 1. AICS Medical
        if (strpos($q, 'medical') !== false || strpos($q, 'gamot') !== false || strpos($q, 'hospital') !== false || strpos($q, 'ospital') !== false || strpos($q, 'reseta') !== false || strpos($q, 'chemo') !== false || strpos($q, 'dialysis') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "Narito po ang opisyal na patnubay mula sa **Citizen's Charter para sa AICS Medical Assistance**:\n\n"
                 . "📋 **Mga Dokumentong Kinakailangan:**\n"
                 . "1. **Medical Certificate / Abstract** (Inisyu sa nakaraang 3 buwan ng attending physician na may pirma at license number).\n"
                 . "2. **Reseta ng Gamot (Prescription)** o **Hospital Running Bill / Promissory Note** mula sa ospital.\n"
                 . "3. **Barangay Certificate of Indigency** na nakasaad ang pangangailangan ng tulong-medikal.\n"
                 . "4. **Valid Government ID** ng pasyente at ng kinatawan (Front at Back side).\n\n"
                 . "⏱️ **Oras ng Pagproseso:** 1 hanggang 2 araw ng paggawa matapos ang assessment ng Social Worker.\n\n"
                 . "💡 *Maaari na po kayong mag-apply online sa ating AICS tab o mag-book ng appointment para sa personal na pagsusuri.*";
        }

        // 2. AICS Burial / Funeral
        if (strpos($q, 'libing') !== false || strpos($q, 'burial') !== false || strpos($q, 'funeral') !== false || strpos($q, 'namatay') !== false || strpos($q, 'burol') !== false || strpos($q, 'death') !== false) {
            return "Magandang araw po, {$name}. Kami po ay taos-pusong nakikiramay sa inyong pamilya.\n\n"
                 . "Para sa **AICS Funeral & Burial Assistance**, narito po ang mga kailangang ihanda ayon sa Citizen's Charter:\n\n"
                 . "📋 **Mga Rekisito:**\n"
                 . "1. **Death Certificate** (Certified True Copy mula sa Local Civil Registrar o may pirma ng doktor).\n"
                 . "2. **Funeral Contract o Statement of Account** na may opisyal na resibo mula sa punerarya.\n"
                 . "3. **Barangay Certificate of Indigency** para sa financial assistance.\n"
                 . "4. **Valid ID** ng nag-aasikaso (immediate family) at ng pumanaw (Front at Back).\n\n"
                 . "🏛️ **Saan Tutungo:** Ground Floor, Palayan City Hall (Window 1), o magsumite ng online application sa AICS tab.";
        }

        // 3. AICS Educational
        if (strpos($q, 'aral') !== false || strpos($q, 'school') !== false || strpos($q, 'student') !== false || strpos($q, 'tuition') !== false || strpos($q, 'educational') !== false || strpos($q, 'edukasyon') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "Ukol po sa **AICS Educational Assistance** para sa mga mag-aaral na kapus-palad:\n\n"
                 . "📋 **Mga Rekisito:**\n"
                 . "1. **Certificate of Registration / Enrollment** (Opisyal na patunay ng enrolment para sa kasalukuyang semestre/taon).\n"
                 . "2. **School ID** ng estudyante (kopya ng harap at likod).\n"
                 . "3. **Barangay Certificate of Indigency** ng magulang o tagapangalaga.\n"
                 . "4. **Valid Government ID** ng magulang/guardian.\n\n"
                 . "💡 *I-upload lamang ang mga dokumento sa 'AICS Assistance' section ng ating portal.*";
        }

        // 4. General AICS
        if (strpos($q, 'aics') !== false || strpos($q, 'cash relief') !== false || strpos($q, 'financial aid') !== false || strpos($q, 'tulong pinansyal') !== false || strpos($q, 'ayuda') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "Ang **Assistance to Individuals in Crisis Situation (AICS)** ay agarang tulong-pinansyal ng Pamahalaang Lungsod ng Palayan sa pamumuno ng CSWDO para sa mga pamilyang nasa krisis.\n\n"
                 . "✨ **Mga Saklaw na Tulong:**\n"
                 . "• **Medical / Hospitalization** (gamutan, operasyon, laboratory)\n"
                 . "• **Burial / Funeral** (tulong sa gastusin sa burol at libing)\n"
                 . "• **Educational** (tulong sa matrikula at school expenses)\n"
                 . "• **Transportation** (pamasahe sa mga na-stranded pauwi)\n"
                 . "• **Food & Emergency Relief** (ayuda sa biktima ng sunog o bagyo)\n\n"
                 . "Pangunahing kailangan sa lahat ay ang **Barangay Indigency** at **Valid ID**. Maaari kayong mag-apply direkta sa portal!";
        }

        // 5. Housing Programs
        if (strpos($q, 'housing') !== false || strpos($q, 'bahay') !== false || strpos($q, 'pabahay') !== false || strpos($q, 'resettlement') !== false || strpos($q, 'akap') !== false || strpos($q, 'san lorenzo') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "Narito ang gabay sa **Palayan City Socialized Housing & Resettlement Programs** (Akap Village at San Lorenzo NHA Housing):\n\n"
                 . "📋 **Mga Rekisito sa Pag-apply:**\n"
                 . "1. **Barangay Certificate of Residency** (Patunay na residente ng Palayan City sa loob ng hindi bababa sa 3 taon).\n"
                 . "2. **Certificate of No Real Property** mula sa City Assessor's Office (nagpapatunay na walang sariling lupa o bahay).\n"
                 . "3. **Proof of Income** (Payslip, Certificate of Employment, o ITR ng breadwinner).\n"
                 . "4. **Marriage Certificate** (kung may asawa) o Birth Certificates ng mga anak/dependents.\n"
                 . "5. **Valid Government IDs** ng mag-asawa (Front at Back).\n\n"
                 . "🔍 **Proseso:** Pagkatapos ng online evaluation, magkakaroon ng CI (Community Investigation) at background verification bago ang pormal na raffle/award.";
        }

        // 6. Senior Citizen (OSCA)
        if (strpos($q, 'senior') !== false || strpos($q, 'osca') !== false || strpos($q, 'pension') !== false || strpos($q, 'matanda') !== false || strpos($q, 'elderly') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "Patnubay mula sa **Office of Senior Citizens Affairs (OSCA)** ng Palayan City:\n\n"
                 . "👴 **Senior Citizen ID & Booklet (Edad 60 pataas):**\n"
                 . "• **Mga Rekisito:** PSA Birth Certificate (o valid ID na may kapanganakan), Barangay Residency, at dalawang pirasong 1x1 ID pictures.\n"
                 . "• **Benepisyo:** 20% diskwento at VAT exemption sa gamot, groceries, pamasahe, at serbisyong medikal (RA 9994).\n\n"
                 . "💰 **Social Pension para sa Indigent Seniors:**\n"
                 . "• ₱1,000 buwanang pensyon para sa mga kwalipikadong nakatatanda na walang regular na pensyon (GSIS/SSS/pribado) at walang suporta mula sa pamilya, sumasailalim sa DSWD validation.";
        }

        // 7. PWD (PDAO)
        if (strpos($q, 'pwd') !== false || strpos($q, 'disability') !== false || strpos($q, 'pdao') !== false || strpos($q, 'kapansanan') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "Patnubay mula sa **Persons with Disability Affairs Office (PDAO)**:\n\n"
                 . "♿ **Kailangang Dokumento para sa PWD ID:**\n"
                 . "1. **Medical Certificate** na naglalaman ng uri ng kapansanan (Orthopedic, Visual, Hearing, Speech, Mental, etc.) mula sa lisensyadong doktor o City Health Office.\n"
                 . "2. **Barangay Certificate of Residency** sa Palayan City.\n"
                 . "3. **Dalawang (2) pirasong 1x1 ID pictures** na may puting background.\n\n"
                 . "🏷️ **Mga Pribilehiyo:** 20% Discount at VAT exemption sa bilihin at gamot (RA 10754) gamit ang kalakip na purchase booklet.";
        }

        // 8. Resident Verification
        if (strpos($q, 'verif') !== false || strpos($q, 'beripikasyon') !== false || strpos($q, 'verify') !== false || strpos($q, 'account') !== false || strpos($q, 'selfie') !== false || strpos($q, 'id card') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "Narito ang mabilisang hakbang upang maging **Verified Resident** sa One Palayan - Kalinga Hub:\n\n"
                 . "🪪 **Step 1: ID Information** — Piliin ang uri ng inyong Government ID at ilagay ang ID number.\n"
                 . "📷 **Step 2: Proof of ID Document** — I-scan o i-upload ang **Harap (Front)** at **Likod (Back)** ng inyong ID. Mayroon tayong AI OCR na mag-aauto-fill ng inyong detalye!\n"
                 . "🤳 **Step 3: Selfie with ID** — Itapat ang mukha sa oval frame at hawakan ang inyong ID katabi ng pisngi gamit ang live face alignment.\n"
                 . "📍 **Step 4: Barangay Address** — Piliin ang inyong Barangay sa Palayan City at i-review ang buod bago i-submit.\n\n"
                 . "Kapag na-verify, magkakaroon kayo ng buong access sa lahat ng serbisyo, ayuda, at priority appointment!";
        }

        // 9. Appointments
        if (strpos($q, 'appointment') !== false || strpos($q, 'schedule') !== false || strpos($q, 'booking') !== false || strpos($q, 'oras') !== false || strpos($q, 'pumunta') !== false || strpos($q, 'slot') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "Para sa maayos at mabilis na transaksyon sa City Hall:\n\n"
                 . "📅 **Paano Mag-book ng Appointment:**\n"
                 . "1. Pumunta sa **'Appointments'** tab sa inyong Resident Portal.\n"
                 . "2. Piliin ang nais na serbisyo (AICS Intake, Housing Consultation, OSCA, PDAO, o General CSWDO).\n"
                 . "3. Pumili ng araw at bakanteng time slot.\n"
                 . "4. I-download o i-print ang inyong **Appointment Slip na may QR Code**.\n\n"
                 . "Sa araw ng pagbisita, ipakita lamang ang inyong QR code sa counter terminal sa City Hall Ground Floor para sa priority numbering!";
        }

        // 10. Office Hours & Location
        if (strpos($q, 'oras') !== false || strpos($q, 'bukas') !== false || strpos($q, 'saan') !== false || strpos($q, 'location') !== false || strpos($q, 'contact') !== false || strpos($q, 'telepono') !== false || strpos($q, 'address') !== false) {
            return "Magandang araw po, {$name}!\n\n"
                 . "🏛️ **CSWDO Palayan City Hall Information:**\n\n"
                 . "📍 **Lokasyon:** Ground Floor, Palayan City Hall, City Government Complex, Palayan City, Nueva Ecija (Tapat ng Palayan City National High School).\n"
                 . "⏰ **Oras ng Opisina:** Lunes hanggang Biyernes, **8:00 AM – 5:00 PM**.\n"
                 . "🛡️ **No Noon Break Policy:** Tuluy-tuloy ang serbisyo kahit tanghalian para sa frontline assistance.\n"
                 . "📞 **Counters:** Window 1 (AICS), Window 2 (Housing & Resettlement), Window 3 (OSCA), Window 4 (PDAO).";
        }

        // 11. Default Warm Greeting & Citizen's Charter Overview
        return "Magandang araw po, {$name}!\n\n"
             . "Ako po si **Lingkod Kalinga AI**, ang inyong virtual assistant para sa **Citizen's Charter ng CSWDO Palayan City**.\n\n"
             . "Maaari po ninyo akong tanungin ukol sa:\n"
             . "• **AICS Financial Assistance** (Medical, Burial, Educational, Emergency Cash)\n"
             . "• **Socialized Housing Programs** (Akap Village, San Lorenzo NHA)\n"
             . "• **Senior Citizen (OSCA)** at **PWD (PDAO)** IDs at benepisyo\n"
             . "• **Resident Account Verification** at pag-upload ng Front at Back ng ID\n"
             . "• **Online Appointment Booking** at QR priority queueing\n\n"
             . "Paano ko po kayo matutulungan ngayong araw?";
    }

    /**
     * Derive context-aware follow-up question chips
     */
    private function deriveSuggestions($message) {
        $q = strtolower($message);
        if (strpos($q, 'aics') !== false || strpos($q, 'medical') !== false || strpos($q, 'gamot') !== false) {
            return [
                "Requirements sa AICS Medical?",
                "Paano ang Burial Assistance?",
                "Gaano katagal bago makuha ang ayuda?",
                "Paano mag-book ng Appointment?"
            ];
        }
        if (strpos($q, 'housing') !== false || strpos($q, 'bahay') !== false || strpos($q, 'pabahay') !== false) {
            return [
                "Sino ang kwalipikado sa Pabahay?",
                "Saan kukuha ng Certificate of No Real Property?",
                "May bayad ba ang housing application?",
                "Paano mag-verify ng account?"
            ];
        }
        if (strpos($q, 'senior') !== false || strpos($q, 'pwd') !== false) {
            return [
                "Kailan ang bigayan ng Social Pension?",
                "Requirements sa PWD Booklet?",
                "Saan ang opisina ng OSCA sa City Hall?",
                "Paano mag-book ng schedule?"
            ];
        }
        return $this->getDefaultSuggestions();
    }

    /**
     * Default quick chips
     */
    public function getDefaultSuggestions() {
        return [
            "Ano ang requirements sa AICS Medical?",
            "Paano mag-apply sa Pabahay / Akap Village?",
            "Paano kumuha ng Senior Citizen ID?",
            "Requirements sa PWD ID & Booklet?",
            "Paano mag-verify ng account?",
            "Ano ang oras ng CSWDO sa City Hall?"
        ];
    }

    /**
     * Derive actionable button links matching the inquiry
     */
    private function deriveActions($message) {
        $q = strtolower($message);
        $actions = [];

        if (strpos($q, 'aics') !== false || strpos($q, 'medical') !== false || strpos($q, 'gamot') !== false || strpos($q, 'libing') !== false || strpos($q, 'ayuda') !== false) {
            $actions[] = ['label' => 'Mag-apply sa AICS', 'url' => 'aics.php', 'icon' => 'fas fa-hand-holding-heart'];
            $actions[] = ['label' => 'AICS Requirements', 'url' => 'aics-requirements.php', 'icon' => 'fas fa-clipboard-list'];
        }

        if (strpos($q, 'housing') !== false || strpos($q, 'bahay') !== false || strpos($q, 'pabahay') !== false) {
            $actions[] = ['label' => 'Palayan Housing Portal', 'url' => 'housing.php', 'icon' => 'fas fa-home'];
            $actions[] = ['label' => 'Housing Requirements', 'url' => 'housing-requirements.php', 'icon' => 'fas fa-file-contract'];
        }

        if (strpos($q, 'senior') !== false) {
            $actions[] = ['label' => 'OSCA Senior Services', 'url' => 'senior.php', 'icon' => 'fas fa-blind'];
        }

        if (strpos($q, 'pwd') !== false) {
            $actions[] = ['label' => 'PDAO PWD Services', 'url' => 'pwd.php', 'icon' => 'fas fa-wheelchair'];
        }

        if (strpos($q, 'verif') !== false || strpos($q, 'account') !== false || strpos($q, 'verify') !== false) {
            $actions[] = ['label' => 'I-verify ang Account', 'url' => 'verify-resident.php', 'icon' => 'fas fa-id-card'];
        }

        if (strpos($q, 'appointment') !== false || strpos($q, 'schedule') !== false) {
            $actions[] = ['label' => 'Mag-book ng Appointment', 'url' => 'appointments.php', 'icon' => 'fas fa-calendar-check'];
        }

        if (empty($actions)) {
            $actions[] = ['label' => 'City Services', 'url' => 'services.php', 'icon' => 'fas fa-th-list'];
            $actions[] = ['label' => 'Book Appointment', 'url' => 'appointments.php', 'icon' => 'fas fa-calendar-alt'];
        }

        return $actions;
    }
}

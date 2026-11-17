<?php
/**
 * id-ocr-ajax.php
 * Philippine Government ID Card OCR Parser & Extraction Endpoint
 * One Palayan-Kalinga Hub - CSWDO Resident Verification System
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ocr_config.php';

if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required to access ID OCR scanner.'
    ]);
    exit;
}

// Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method. Expected POST.'
    ]);
    exit;
}

$raw_text = trim($_POST['raw_text'] ?? '');
$image_base64 = trim($_POST['image_base64'] ?? '');

// If image_base64 is provided and Google Vision is configured, call Google Vision
if (empty($raw_text) && !empty($image_base64) && defined('GOOGLE_VISION_API_KEY') && !empty(GOOGLE_VISION_API_KEY)) {
    try {
        $clean_b64 = preg_replace('/^data:image\/\w+;base64,/', '', $image_base64);
        $payload = json_encode([
            'requests' => [
                [
                    'image' => ['content' => $clean_b64],
                    'features' => [['type' => 'TEXT_DETECTION', 'maxResults' => 1]]
                ]
            ]
        ]);

        $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . GOOGLE_VISION_API_KEY;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $json = json_decode($response, true);
            $raw_text = $json['responses'][0]['textAnnotations'][0]['description'] ?? '';
        }
    } catch (Exception $e) {
        error_log('Google Vision OCR error: ' . $e->getMessage());
    }
}

if (empty($raw_text)) {
    echo json_encode([
        'success' => false,
        'error' => 'No readable text or image payload provided for OCR extraction.'
    ]);
    exit;
}

/**
 * Specialized Philippine Government ID Parser
 */
function parsePhilippineIdCard(string $text): array {
    $clean_text = preg_replace('/\r\n|\r/', "\n", $text);
    $upper_text = strtoupper($clean_text);
    $lines = array_filter(array_map('trim', explode("\n", $clean_text)));

    // Official Palayan City Barangays
    $palayan_barangays = [
        'Atate', 'Aulo', 'Bagong Buhay', 'Baryo Militar', 'Caballero', 'Caimito', 
        'Doña Josefa', 'Ganaderia', 'Imelda Valley', 'Langka', 'Malate', 'Maligaya', 
        'Manacnac', 'Mapaet', 'Marcos Village', 'Popolon', 'Santolan', 'Sapang Buho', 'Singalat'
    ];

    $detected_type = 'Other';
    $type_confidence = 50;

    // 1. Detect ID Type
    if (str_contains($upper_text, 'PHILIPPINE IDENTIFICATION') || 
        str_contains($upper_text, 'PHILSYS') || 
        str_contains($upper_text, 'PAMBANSANG PAGKAKAKILANLAN') ||
        str_contains($upper_text, 'REPUBLIKA NG PILIPINAS') && str_contains($upper_text, 'CARD NUMBER')) {
        $detected_type = 'PhilSys ID';
        $type_confidence = 98;
    } elseif (str_contains($upper_text, "DRIVER'S LICENSE") || str_contains($upper_text, 'LAND TRANSPORTATION OFFICE') || str_contains($upper_text, 'LTO')) {
        $detected_type = "Driver's License";
        $type_confidence = 96;
    } elseif (str_contains($upper_text, "VOTER") || str_contains($upper_text, 'COMELEC') || str_contains($upper_text, 'COMMISSION ON ELECTIONS')) {
        $detected_type = "Voter's ID";
        $type_confidence = 94;
    } elseif (str_contains($upper_text, 'UNIFIED MULTI-PURPOSE') || str_contains($upper_text, 'UMID') || str_contains($upper_text, 'CRN')) {
        $detected_type = 'UMID';
        $type_confidence = 92;
    } elseif (str_contains($upper_text, 'POSTAL ID') || str_contains($upper_text, 'PHILPOST') || str_contains($upper_text, 'PHILIPPINE POSTAL')) {
        $detected_type = 'Postal ID';
        $type_confidence = 92;
    } elseif (str_contains($upper_text, 'SENIOR CITIZEN') || str_contains($upper_text, 'OSCA')) {
        $detected_type = 'Senior Citizen ID';
        $type_confidence = 90;
    } elseif (str_contains($upper_text, 'PERSONS WITH DISABILITY') || str_contains($upper_text, 'PWD') || str_contains($upper_text, 'PDAO')) {
        $detected_type = 'PWD ID';
        $type_confidence = 90;
    } elseif (str_contains($upper_text, 'BARANGAY') || str_contains($upper_text, 'RESIDENT ID')) {
        $detected_type = 'Barangay ID';
        $type_confidence = 88;
    } elseif (str_contains($upper_text, 'PASSPORT') || str_contains($upper_text, 'PASAPORTE')) {
        $detected_type = 'Passport';
        $type_confidence = 95;
    }

    // 2. Detect ID Number
    $detected_number = '';
    
    // Pattern A: PhilSys 16 digits formatted XXXX-XXXX-XXXX-XXXX
    if ($detected_type === 'PhilSys ID' || empty($detected_number)) {
        if (preg_match('/\b(\d{4})[-\s](\d{4})[-\s](\d{4})[-\s](\d{4})\b/', $text, $m)) {
            $detected_number = "{$m[1]}-{$m[2]}-{$m[3]}-{$m[4]}";
            if ($detected_type === 'Other') $detected_type = 'PhilSys ID';
        }
    }

    // Pattern B: Driver's license format X0X-XX-XXXXXX or letter + 2 digits - 2 digits - 6 digits
    if (empty($detected_number) || $detected_type === "Driver's License") {
        if (preg_match('/\b([A-Z]\d{2})[-\s]?(\d{2})[-\s]?(\d{6})\b/', $upper_text, $m)) {
            $detected_number = "{$m[1]}-{$m[2]}-{$m[3]}";
            $detected_type = "Driver's License";
        }
    }

    // Pattern C: UMID / CRN format XXXX-XXXXXXX-X
    if (empty($detected_number) || $detected_type === 'UMID') {
        if (preg_match('/\b(\d{4})[-\s]?(\d{7})[-\s]?(\d)\b/', $text, $m)) {
            $detected_number = "{$m[1]}-{$m[2]}-{$m[3]}";
            $detected_type = 'UMID';
        }
    }

    // Pattern D: Voter's VIN e.g. 4901-0021A-H1585ABC
    if (empty($detected_number) || $detected_type === "Voter's ID") {
        if (preg_match('/\b(\d{4}[-\s]?\d{4}[A-Z][-\s]?[A-Z0-9]+)\b/', $upper_text, $m)) {
            $detected_number = $m[1];
            $detected_type = "Voter's ID";
        }
    }

    // Pattern E: Generic labeled ID numbers: ID NO, CARD NO, LICENSE NO, NO.:
    if (empty($detected_number)) {
        if (preg_match('/(?:ID\s*(?:NO|NUMBER|#)|CARD\s*NO|NO\.?)\s*[:.]?\s*([A-Z0-9\-]{5,20})/i', $text, $m)) {
            $detected_number = trim($m[1]);
        }
    }

    // Pattern F: Fallback to any distinct 8-16 digit sequence
    if (empty($detected_number)) {
        if (preg_match('/\b(\d{4}[-\s]?\d{4}[-\s]?\d{4})\b/', $text, $m)) {
            $detected_number = str_replace(' ', '-', $m[1]);
        }
    }

    // 3. Detect Palayan City Barangay
    $detected_barangay = '';
    foreach ($palayan_barangays as $brgy) {
        if (stripos($upper_text, strtoupper($brgy)) !== false) {
            $detected_barangay = $brgy;
            break;
        }
    }

    // 4. Detect Full Name
    $detected_name = '';
    // Check PhilSys format (Apelyido / Last Name, Mga Pangalan / Given Names)
    if (preg_match('/(?:APELYIDO|LAST\s*NAME)\s*[:.\n\r]*([A-Z\s]{2,30})/i', $clean_text, $m)) {
        $last_name = trim($m[1]);
        if (preg_match('/(?:MGA\s*PANGALAN|FIRST\s*NAME|GIVEN\s*NAMES)\s*[:.\n\r]*([A-Z\s]{2,30})/i', $clean_text, $m2)) {
            $first_name = trim($m2[1]);
            $detected_name = trim($first_name . ' ' . $last_name);
        } else {
            $detected_name = $last_name;
        }
    } elseif (preg_match('/(?:NAME|PANGALAN)\s*[:.\n\r]*([A-Z\s,]{4,35})/i', $clean_text, $m)) {
        $detected_name = trim(preg_replace('/[^A-Za-z\s,]/', '', $m[1]));
    }

    // 5. Detect Date of Birth
    $detected_dob = '';
    if (preg_match('/(?:BIRTH|KAPANGANAKAN|DOB)\s*[:.\n\r]*(\d{4}[-\/]\d{2}[-\/]\d{2}|\d{2}[-\/]\d{2}[-\/]\d{4}|[A-Za-z]{3,9}\s+\d{1,2},?\s+\d{4})/i', $clean_text, $m)) {
        try {
            $dt = new DateTime($m[1]);
            $detected_dob = $dt->format('Y-m-d');
        } catch (Exception $e) {}
    }

    $extracted_count = 0;
    if (!empty($detected_type) && $detected_type !== 'Other') $extracted_count++;
    if (!empty($detected_number)) $extracted_count++;
    if (!empty($detected_barangay)) $extracted_count++;
    if (!empty($detected_name)) $extracted_count++;
    if (!empty($detected_dob)) $extracted_count++;

    return [
        'id_type' => $detected_type,
        'id_number' => $detected_number,
        'name' => $detected_name,
        'birth_date' => $detected_dob,
        'barangay' => $detected_barangay,
        'city' => 'Palayan City',
        'confidence' => min(98, max(50, ($extracted_count * 20) + 15)),
        'fields_count' => $extracted_count,
        'raw_snippet' => substr(preg_replace('/\s+/', ' ', $clean_text), 0, 160)
    ];
}

$parsed = parsePhilippineIdCard($raw_text);

echo json_encode([
    'success' => true,
    'data' => $parsed
]);

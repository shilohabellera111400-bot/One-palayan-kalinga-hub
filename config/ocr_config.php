<?php
/**
 * ID Card OCR Configuration
 * One Palayan-Kalinga Hub - CSWDO Resident Verification System
 */

// Primary OCR Engine: 'hybrid' (Client-side Tesseract.js with server parsing), 'tesseract', or 'google_vision'
if (!defined('OCR_DEFAULT_ENGINE')) {
    define('OCR_DEFAULT_ENGINE', 'hybrid');
}

// Optional Google Cloud Vision API Key (if provided by LGU for cloud-level OCR)
if (!defined('GOOGLE_VISION_API_KEY')) {
    define('GOOGLE_VISION_API_KEY', getenv('GOOGLE_VISION_API_KEY') ?: '');
}

// Auto-fill form fields upon successful extraction
if (!defined('OCR_AUTO_FILL_ENABLED')) {
    define('OCR_AUTO_FILL_ENABLED', true);
}

// Minimum confidence threshold (0 - 100)
if (!defined('OCR_MIN_CONFIDENCE')) {
    define('OCR_MIN_CONFIDENCE', 50);
}

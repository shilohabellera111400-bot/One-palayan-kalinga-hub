<?php
/**
 * Google Gemini API Configuration
 * One Palayan-Kalinga Hub - CSWDO AI Citizen's Charter Chatbot
 */

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-1.5-flash');
}

if (!defined('GEMINI_CHATBOT_ENABLED')) {
    define('GEMINI_CHATBOT_ENABLED', true);
}

if (!defined('GEMINI_API_TIMEOUT')) {
    define('GEMINI_API_TIMEOUT', 15); // seconds
}

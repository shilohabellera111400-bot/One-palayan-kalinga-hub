<?php
/**
 * OpenAI API Configuration
 * One Palayan-Kalinga Hub - CSWDO Helpdesk AI Integration
 */

// Default configuration constants (can also be configured via Admin Panel > Settings)
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', getenv('OPENAI_API_KEY') ?: '');
}

if (!defined('OPENAI_MODEL')) {
    define('OPENAI_MODEL', 'gpt-4o-mini');
}

if (!defined('OPENAI_HELPDESK_ENABLED')) {
    define('OPENAI_HELPDESK_ENABLED', true);
}

if (!defined('OPENAI_API_TIMEOUT')) {
    define('OPENAI_API_TIMEOUT', 15); // seconds
}

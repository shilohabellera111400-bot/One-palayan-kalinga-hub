<?php
/**
 * config/features.php
 * System Scope & Feature Toggles
 * 
 * Allows temporary scope reduction (e.g. focusing solely on Crisis Assistance / AICS,
 * Housing & Resettlement, Appointments, and Document Pre-verification) without deleting
 * any underlying code, database schemas, records, or UI designs.
 * 
 * To RE-ENABLE PWD and Senior Citizen services instantly (e.g. for defense or professor request):
 * Change FEATURE_ENABLE_PWD_SENIOR to true.
 */

if (!defined('FEATURE_ENABLE_PWD_SENIOR')) {
    define('FEATURE_ENABLE_PWD_SENIOR', false);
}

/**
 * Helper to check whether Senior & PWD services are active in the system scope.
 */
function isPwdSeniorScopeActive(): bool {
    return defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR === true;
}

/**
 * Universal feature check helper.
 */
if (!function_exists('is_feature_enabled')) {
    function is_feature_enabled(string $feature): bool {
        if ($feature === 'pwd_senior' || $feature === 'pwd' || $feature === 'senior') {
            return isPwdSeniorScopeActive();
        }
        return false;
    }
}

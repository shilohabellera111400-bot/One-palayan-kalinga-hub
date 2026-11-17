<?php
/**
 * CLI & Web Toggle Utility for PWD and Senior Citizen Services
 * Usage via CLI:
 *   php toggle_scope.php on   -> Enables PWD & Senior Services
 *   php toggle_scope.php off  -> Disables PWD & Senior Services
 */

require_once __DIR__ . '/../../config/database.php';

$featuresFile = __DIR__ . '/../../config/features.php';
if (!file_exists($featuresFile)) {
    die("Features file not found at: $featuresFile\n");
}

$action = $argv[1] ?? ($_GET['action'] ?? 'status');
$action = strtolower(trim($action));

$content = file_get_contents($featuresFile);

if ($action === 'on' || $action === 'enable') {
    $content = preg_replace(
        "/define\('FEATURE_ENABLE_PWD_SENIOR',\s*(false|true)\);/i",
        "define('FEATURE_ENABLE_PWD_SENIOR', true);",
        $content
    );
    file_put_contents($featuresFile, $content);

    // Re-activate in database services table
    try {
        $db = (new Database())->getConnection();
        $db->query("UPDATE services SET is_active = 1 WHERE service_id IN (2, 3)");
    } catch (Exception $e) {}

    echo "SUCCESS: Senior Citizen and PWD Services are now ENABLED.\n";
} elseif ($action === 'off' || $action === 'disable') {
    $content = preg_replace(
        "/define\('FEATURE_ENABLE_PWD_SENIOR',\s*(false|true)\);/i",
        "define('FEATURE_ENABLE_PWD_SENIOR', false);",
        $content
    );
    file_put_contents($featuresFile, $content);

    // Deactivate in database services table
    try {
        $db = (new Database())->getConnection();
        $db->query("UPDATE services SET is_active = 0 WHERE service_id IN (2, 3)");
    } catch (Exception $e) {}

    echo "SUCCESS: Senior Citizen and PWD Services are now DISABLED (Scope reduced).\n";
} else {
    require_once $featuresFile;
    $state = (defined('FEATURE_ENABLE_PWD_SENIOR') && FEATURE_ENABLE_PWD_SENIOR) ? "ENABLED (Active)" : "DISABLED (Archived/Hidden)";
    echo "Current Status: Senior Citizen and PWD Services are $state.\n";
    echo "Usage:\n  php toggle_scope.php on   (To enable)\n  php toggle_scope.php off  (To disable)\n";
}

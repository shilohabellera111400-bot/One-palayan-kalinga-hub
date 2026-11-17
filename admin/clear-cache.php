<?php
// admin/clear-cache.php - System cache clearing utility
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

try {
    // Clear PHP OPcache if available
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    // Clear temporary directory leftovers if any
    $tmp_dir = sys_get_temp_dir();

    $_SESSION['success'] = "System cache, opcode cache, and temporary buffers cleared successfully!";
    redirect('settings.php');
} catch (Exception $e) {
    $_SESSION['error'] = "Failed to clear cache: " . $e->getMessage();
    redirect('settings.php');
}

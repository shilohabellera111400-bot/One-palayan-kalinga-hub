<?php
// admin/optimize-db.php - Database table optimization utility
require_once __DIR__ . '/../config/database.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$database = new Database();
$db = $database->getConnection();

try {
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $optimized = 0;
    foreach ($tables as $table) {
        $db->query("OPTIMIZE TABLE `{$table}`");
        $optimized++;
    }

    $_SESSION['success'] = "Database optimization completed successfully! ({$optimized} tables optimized)";
    redirect('settings.php');
} catch (Exception $e) {
    $_SESSION['error'] = "Optimization failed: " . $e->getMessage();
    redirect('settings.php');
}

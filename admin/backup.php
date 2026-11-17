<?php
// admin/backup.php - Database backup generator for One Palayan-Kalinga Hub
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

    $backup_sql = "-- One Palayan-Kalinga Hub Database Backup\n";
    $backup_sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $backup_sql .= "-- Environment: " . (defined('APP_URL') ? APP_URL : 'localhost') . "\n";
    $backup_sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $backup_sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $backup_sql .= "SET time_zone = \"+08:00\";\n\n";

    foreach ($tables as $table) {
        $create_stmt = $db->query("SHOW CREATE TABLE `{$table}`");
        $create_row = $create_stmt->fetch(PDO::FETCH_NUM);
        
        $backup_sql .= "\n-- Table structure for table `{$table}`\n";
        $backup_sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $backup_sql .= $create_row[1] . ";\n\n";

        // Dump data
        $data_stmt = $db->query("SELECT * FROM `{$table}`");
        $rows = $data_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $backup_sql .= "-- Dumping data for table `{$table}`\n";
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $escaped_cols = array_map(function($c) { return "`$c`"; }, $cols);
                $escaped_vals = array_map(function($v) use ($db) {
                    if ($v === null) return "NULL";
                    return $db->quote($v);
                }, array_values($row));

                $backup_sql .= "INSERT INTO `{$table}` (" . implode(', ', $escaped_cols) . ") VALUES (" . implode(', ', $escaped_vals) . ");\n";
            }
            $backup_sql .= "\n";
        }
    }

    $backup_sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = 'palayan_kalinga_backup_' . date('Y_m_d_His') . '.sql';

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($backup_sql));
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $backup_sql;
    exit();
} catch (Exception $e) {
    $_SESSION['error'] = "Backup failed: " . $e->getMessage();
    redirect('settings.php');
}

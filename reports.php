<?php
require_once __DIR__ . '/config/database.php';

// Redirect to canonical staff reports interface
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: staff/reports.php' . $qs);
exit();
<?php
require_once __DIR__ . '/config/database.php';

// Redirect to canonical staff AICS management interface
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: staff/manage-aics.php' . $qs);
exit();
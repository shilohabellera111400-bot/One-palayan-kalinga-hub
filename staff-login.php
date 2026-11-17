<?php
// staff-login.php - Canonical redirect to unified login page
require_once __DIR__ . '/config/database.php';

$query_string = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: login.php' . $query_string, true, 301);
exit();

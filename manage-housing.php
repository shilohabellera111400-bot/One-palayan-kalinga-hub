<?php
require_once __DIR__ . '/config/database.php';

// Redirect to canonical staff housing management interface
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: staff/manage-housing.php' . $qs);
exit();
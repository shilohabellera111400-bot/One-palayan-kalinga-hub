<?php
require_once __DIR__ . '/config/database.php';

// Redirect to canonical staff appointments management interface
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: staff/manage-appointments.php' . $qs);
exit();
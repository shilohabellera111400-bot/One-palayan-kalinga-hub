<?php
require_once __DIR__ . '/config/database.php';

// Redirect to canonical staff document verification interface
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: staff/verify-documents.php' . $qs);
exit();
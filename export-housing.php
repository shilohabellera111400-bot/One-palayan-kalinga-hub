<?php
// export-housing.php - Redirect to staff housing export handler
require_once __DIR__ . '/config/database.php';

$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: staff/export-housing.php' . $qs);
exit();

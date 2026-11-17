<?php
// housing-applications.php - Consolidated into canonical my-housing.php
require_once __DIR__ . '/config/database.php';

$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: my-housing.php' . $qs);
exit();

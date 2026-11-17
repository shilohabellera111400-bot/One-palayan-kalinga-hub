<?php
require_once __DIR__ . '/config/database.php';

// Redirect to canonical staff housing kanban interface
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: staff/kanban-housing.php' . $qs);
exit();

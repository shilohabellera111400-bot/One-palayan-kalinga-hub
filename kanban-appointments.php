<?php
// kanban-appointments.php
require_once __DIR__ . '/config/database.php';

$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: staff/kanban-appointments.php' . $qs);
exit();

<?php
require '../config/db.php';
require '../config/helpers.php';

$carId = post_int('car_id', true);
require_car($pdo, $carId);
$priority = require_allowed_value(post_string('priority', true), ['Low','Medium','High'], 'priority');
$status = require_allowed_value(post_string('status', true), ['To Do','In Progress','Done'], 'status');

$stmt = $pdo->prepare("INSERT INTO tasks (car_id, task_title, description, assigned_to, priority, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$carId, post_string('task_title', true), post_string('description'), post_string('assigned_to'), $priority, $status, post_date_or_null('due_date')]);
header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

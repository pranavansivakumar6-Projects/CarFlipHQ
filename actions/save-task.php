<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$carId = post_int('car_id', true);
require_car($pdo, $carId);
$priority = require_allowed_value(post_string('priority', true), ['Low','Medium','High'], 'priority');
$status = require_allowed_value(post_string('status', true), ['To Do','In Progress','Done'], 'status');

$taskPhoto = save_uploaded_image('task_photo', 'tasks');
$stmt = $pdo->prepare("INSERT INTO tasks (car_id, task_title, description, assigned_to, priority, status, due_date, task_photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$carId, post_string('task_title', true), post_string('description'), post_string('assigned_to'), $priority, $status, post_date_or_null('due_date'), $taskPhoto]);
header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

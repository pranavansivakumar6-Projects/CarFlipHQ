<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT car_id FROM tasks WHERE id = ?');
$stmt->execute([$id]);
$carId = $stmt->fetchColumn();
if (!$carId) { http_response_code(404); die('Task not found.'); }

$priority = require_allowed_value(post_string('priority', true), ['Low','Medium','High'], 'priority');
$status = require_allowed_value(post_string('status', true), ['To Do','In Progress','Done'], 'status');
$hoursSpent = post_money('hours_spent');
$taskPhoto = save_uploaded_image('task_photo', 'tasks');

if ($taskPhoto) {
    $oldStmt = $pdo->prepare('SELECT task_photo FROM tasks WHERE id = ?');
    $oldStmt->execute([$id]);
    delete_uploaded_file($oldStmt->fetchColumn());

    $stmt = $pdo->prepare('UPDATE tasks SET task_title = ?, description = ?, assigned_to = ?, priority = ?, status = ?, hours_spent = ?, due_date = ?, task_photo = ? WHERE id = ?');
    $stmt->execute([post_string('task_title', true), post_string('description'), post_string('assigned_to'), $priority, $status, $hoursSpent, post_date_or_null('due_date'), $taskPhoto, $id]);
} else {
    $stmt = $pdo->prepare('UPDATE tasks SET task_title = ?, description = ?, assigned_to = ?, priority = ?, status = ?, hours_spent = ?, due_date = ? WHERE id = ?');
    $stmt->execute([post_string('task_title', true), post_string('description'), post_string('assigned_to'), $priority, $status, $hoursSpent, post_date_or_null('due_date'), $id]);
}

header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

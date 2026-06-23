<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT car_id, task_photo FROM tasks WHERE id = ?');
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) { http_response_code(404); die('Task not found.'); }

$stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
$stmt->execute([$id]);
delete_uploaded_file($task['task_photo']);

header('Location: ../pages/car-detail.php?id=' . (int) $task['car_id']);
exit;
?>

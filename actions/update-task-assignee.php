<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_permission('can_manage_tasks');

$id = post_int('id', true);
$assignedTo = post_user_names($pdo, 'assigned_to');

$stmt = $pdo->prepare('SELECT car_id FROM tasks WHERE id = ?');
$stmt->execute([$id]);
$carId = $stmt->fetchColumn();
if (!$carId) { http_response_code(404); die('Task not found.'); }

$stmt = $pdo->prepare('UPDATE tasks SET assigned_to = ? WHERE id = ?');
$stmt->execute([$assignedTo, $id]);

header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

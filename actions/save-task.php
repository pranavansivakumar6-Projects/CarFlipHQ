<?php
require '../config/db.php';
$stmt = $pdo->prepare("INSERT INTO tasks (car_id, task_title, description, assigned_to, priority, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$_POST['car_id'], $_POST['task_title'], $_POST['description'], $_POST['assigned_to'], $_POST['priority'], $_POST['status'], $_POST['due_date'] ?: null]);
header('Location: ../pages/car-detail.php?id=' . $_POST['car_id']);
exit;
?>

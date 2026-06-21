<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$id = post_int('id', true);
$assignedTo = post_string('assigned_to');

$stmt = $pdo->prepare('SELECT car_id FROM tasks WHERE id = ?');
$stmt->execute([$id]);
$carId = $stmt->fetchColumn();
if (!$carId) { http_response_code(404); die('Task not found.'); }

if ($assignedTo !== '') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE name = ?');
    $stmt->execute([$assignedTo]);
    if (!$stmt->fetchColumn()) {
        http_response_code(400);
        die('Assigned user is invalid.');
    }
}

$stmt = $pdo->prepare('UPDATE tasks SET assigned_to = ? WHERE id = ?');
$stmt->execute([$assignedTo, $id]);

header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

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

$stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ?');
$stmt->execute([$id]);

header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

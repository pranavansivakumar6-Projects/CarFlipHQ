<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_permission('can_manage_finance');

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT car_id FROM car_purchase_payments WHERE id = ?');
$stmt->execute([$id]);
$carId = $stmt->fetchColumn();
if (!$carId) { http_response_code(404); die('Purchase payment not found.'); }

$stmt = $pdo->prepare('DELETE FROM car_purchase_payments WHERE id = ?');
$stmt->execute([$id]);

header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

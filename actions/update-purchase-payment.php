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
require_car($pdo, (int) $carId);

$paidBy = post_string('paid_by', true);
$payerStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE name = ?');
$payerStmt->execute([$paidBy]);
if (!$payerStmt->fetchColumn()) {
    http_response_code(400);
    die('Paid by user is invalid.');
}

$stmt = $pdo->prepare('UPDATE car_purchase_payments SET paid_by = ?, amount = ?, paid_date = ?, notes = ? WHERE id = ?');
$stmt->execute([$paidBy, post_money('amount', true), post_date_or_null('paid_date'), post_string('notes'), $id]);

header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

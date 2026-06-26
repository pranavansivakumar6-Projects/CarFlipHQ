<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_permission('can_manage_finance');

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT car_id, receipt_file FROM expenses WHERE id = ?');
$stmt->execute([$id]);
$expense = $stmt->fetch();
if (!$expense) { http_response_code(404); die('Expense not found.'); }
require_car($pdo, (int) $expense['car_id']);

$stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
$stmt->execute([$id]);
delete_uploaded_file($expense['receipt_file']);

header('Location: ../pages/car-detail.php?id=' . (int) $expense['car_id']);
exit;
?>

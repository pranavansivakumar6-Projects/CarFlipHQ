<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$carId = post_int('car_id', true);
require_car($pdo, $carId);
$paidBy = post_string('paid_by', true);

$payerStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE name = ?');
$payerStmt->execute([$paidBy]);
if (!$payerStmt->fetchColumn()) {
    http_response_code(400);
    die('Paid by user is invalid.');
}

$stmt = $pdo->prepare('INSERT INTO car_purchase_payments (car_id, paid_by, amount, paid_date, notes) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$carId, $paidBy, post_money('amount', true), post_date_or_null('paid_date'), post_string('notes')]);

header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

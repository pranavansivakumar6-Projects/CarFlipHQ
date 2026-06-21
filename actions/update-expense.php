<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT car_id FROM expenses WHERE id = ?');
$stmt->execute([$id]);
$carId = $stmt->fetchColumn();
if (!$carId) { http_response_code(404); die('Expense not found.'); }

$category = require_allowed_value(post_string('category', true), ['Parts','Labour','Towing','RWC','Registration','Detailing','Paint','Other'], 'category');
$paidBy = post_string('paid_by', true);
$payerStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE name = ?');
$payerStmt->execute([$paidBy]);
if (!$payerStmt->fetchColumn()) {
    http_response_code(400);
    die('Paid by user is invalid.');
}
$receiptFile = save_uploaded_image('receipt_file', 'expenses');

if ($receiptFile) {
    $oldStmt = $pdo->prepare('SELECT receipt_file FROM expenses WHERE id = ?');
    $oldStmt->execute([$id]);
    delete_uploaded_file($oldStmt->fetchColumn());

    $stmt = $pdo->prepare('UPDATE expenses SET category = ?, expense_name = ?, amount = ?, paid_by = ?, expense_date = ?, notes = ?, receipt_file = ? WHERE id = ?');
    $stmt->execute([$category, post_string('expense_name', true), post_money('amount', true), $paidBy, post_date_or_null('expense_date'), post_string('notes'), $receiptFile, $id]);
} else {
    $stmt = $pdo->prepare('UPDATE expenses SET category = ?, expense_name = ?, amount = ?, paid_by = ?, expense_date = ?, notes = ? WHERE id = ?');
    $stmt->execute([$category, post_string('expense_name', true), post_money('amount', true), $paidBy, post_date_or_null('expense_date'), post_string('notes'), $id]);
}

header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$carId = post_int('car_id', true);
require_car($pdo, $carId);
$category = require_allowed_value(post_string('category', true), ['Parts','Labour','Towing','RWC','Registration','Detailing','Paint','Other'], 'category');

$receiptFile = save_uploaded_image('receipt_file', 'expenses');
$stmt = $pdo->prepare("INSERT INTO expenses (car_id, category, expense_name, amount, expense_date, notes, receipt_file) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$carId, $category, post_string('expense_name', true), post_money('amount', true), post_date_or_null('expense_date'), post_string('notes'), $receiptFile]);
header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

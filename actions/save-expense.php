<?php
require '../config/db.php';
$stmt = $pdo->prepare("INSERT INTO expenses (car_id, category, expense_name, amount, expense_date, notes) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$_POST['car_id'], $_POST['category'], $_POST['expense_name'], $_POST['amount'], $_POST['expense_date'] ?: null, $_POST['notes']]);
header('Location: ../pages/car-detail.php?id=' . $_POST['car_id']);
exit;
?>

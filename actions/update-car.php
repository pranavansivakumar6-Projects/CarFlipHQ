<?php
require '../config/db.php';
$stmt = $pdo->prepare("UPDATE cars SET status=?, estimated_sale_price=?, actual_sale_price=?, sold_date=?, notes=? WHERE id=?");
$stmt->execute([$_POST['status'], $_POST['estimated_sale_price'] ?: 0, $_POST['actual_sale_price'] ?: 0, $_POST['sold_date'] ?: null, $_POST['notes'], $_POST['id']]);
header('Location: ../pages/car-detail.php?id=' . $_POST['id']);
exit;
?>

<?php
require '../config/db.php';
$stmt = $pdo->prepare("INSERT INTO cars (make, model, year, vin, rego, odometer, source, purchase_price, purchase_date, estimated_sale_price, damage_notes, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $_POST['make'], $_POST['model'], $_POST['year'] ?: null, $_POST['vin'], $_POST['rego'], $_POST['odometer'] ?: null,
    $_POST['source'], $_POST['purchase_price'] ?: 0, $_POST['purchase_date'] ?: null, $_POST['estimated_sale_price'] ?: 0,
    $_POST['damage_notes'], $_POST['notes']
]);
header('Location: ../pages/cars.php');
exit;
?>

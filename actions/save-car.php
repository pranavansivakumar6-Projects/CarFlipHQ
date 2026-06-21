<?php
require '../config/db.php';
require '../config/helpers.php';

$stmt = $pdo->prepare("INSERT INTO cars (make, model, year, vin, rego, odometer, source, purchase_price, purchase_date, estimated_sale_price, damage_notes, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([
    post_string('make', true),
    post_string('model', true),
    post_int('year'),
    post_string('vin'),
    post_string('rego'),
    post_int('odometer'),
    post_string('source'),
    post_money('purchase_price'),
    post_date_or_null('purchase_date'),
    post_money('estimated_sale_price'),
    post_string('damage_notes'),
    post_string('notes')
]);
header('Location: ../pages/cars.php');
exit;
?>

<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$carId = post_int('id', true);
require_car($pdo, $carId);
$status = require_allowed_value(post_string('status', true), ['Bought','Waiting for Parts','Under Repair','RWC Pending','Ready for Sale','Listed','Sold'], 'status');

$stmt = $pdo->prepare("UPDATE cars SET status=?, estimated_sale_price=?, actual_sale_price=?, sold_date=?, notes=? WHERE id=?");
$stmt->execute([$status, post_money('estimated_sale_price'), post_money('actual_sale_price'), post_date_or_null('sold_date'), post_string('notes'), $carId]);
header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

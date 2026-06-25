<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_permission('can_manage_tasks');
$carId = post_int('car_id', true);
require_car($pdo, $carId);
$status = require_allowed_value(post_string('status', true), ['Needed','Ordered','Arrived','Installed','Cancelled'], 'status');
$stmt = $pdo->prepare('INSERT INTO parts (car_id, part_name, supplier, cost, status, ordered_date, arrived_date, installed_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$carId, post_string('part_name', true), post_string('supplier'), post_money('cost'), $status, post_date_or_null('ordered_date'), post_date_or_null('arrived_date'), post_date_or_null('installed_date'), post_string('notes')]);
header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

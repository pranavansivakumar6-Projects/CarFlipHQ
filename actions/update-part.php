<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_permission('can_manage_tasks');
$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT car_id FROM parts WHERE id = ?');
$stmt->execute([$id]);
$carId = $stmt->fetchColumn();
if (!$carId) { http_response_code(404); die('Part not found.'); }
require_car($pdo, (int) $carId);
$status = require_allowed_value(post_string('status', true), ['Needed','Ordered','Arrived','Installed','Cancelled'], 'status');
$stmt = $pdo->prepare('UPDATE parts SET part_name = ?, supplier = ?, cost = ?, status = ?, ordered_date = ?, arrived_date = ?, installed_date = ?, notes = ? WHERE id = ?');
$stmt->execute([post_string('part_name', true), post_string('supplier'), post_money('cost'), $status, post_date_or_null('ordered_date'), post_date_or_null('arrived_date'), post_date_or_null('installed_date'), post_string('notes'), $id]);
header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

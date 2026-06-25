<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_once '../config/status.php';

require_permission('can_manage_cars');

$carId = post_int('id', true);
require_car($pdo, $carId);
$status = require_allowed_value(normalise_car_status(post_string('status', true)), allowed_car_statuses(), 'status');

$stmt = $pdo->prepare("UPDATE cars SET make=?, model=?, year=?, color=?, body_type=?, vin=?, rego=?, odometer=?, source=?, purchase_price=?, purchase_date=?, status=?, estimated_sale_price=?, actual_sale_price=?, sold_date=?, damage_notes=?, notes=? WHERE id=?");
$stmt->execute([
    post_string('make', true),
    post_string('model', true),
    post_int('year'),
    post_string('color'),
    post_string('body_type'),
    post_string('vin'),
    post_string('rego'),
    post_int('odometer'),
    post_string('source'),
    post_money('purchase_price'),
    post_date_or_null('purchase_date'),
    $status,
    post_money('estimated_sale_price'),
    post_money('actual_sale_price'),
    post_date_or_null('sold_date'),
    post_string('damage_notes'),
    post_string('notes'),
    $carId,
]);
header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

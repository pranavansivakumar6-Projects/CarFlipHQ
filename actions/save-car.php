<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_permission('can_manage_cars');

$profilePhoto = save_uploaded_image('profile_photo', 'cars');

$stmt = $pdo->prepare("INSERT INTO cars (make, model, year, color, body_type, vin, rego, odometer, source, purchase_price, purchase_date, estimated_sale_price, profile_photo, damage_notes, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
    post_money('estimated_sale_price'),
    $profilePhoto,
    post_string('damage_notes'),
    post_string('notes')
]);

$carId = (int) $pdo->lastInsertId();
$user = current_user();
if (($user['role'] ?? '') === 'admin') {
    sync_car_user_access($pdo, $carId, post_user_ids('access_user_ids'));
} else {
    sync_car_user_access($pdo, $carId, [(int) $user['id']]);
}

header('Location: ../pages/cars.php');
exit;
?>

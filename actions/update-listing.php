<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_permission('can_manage_sales');
$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT car_id FROM sale_listings WHERE id = ?');
$stmt->execute([$id]);
$carId = $stmt->fetchColumn();
if (!$carId) { http_response_code(404); die('Listing not found.'); }
$status = require_allowed_value(post_string('status', true), ['Draft','Listed','Offer Received','Deposit Taken','Sold','Withdrawn'], 'status');
$stmt = $pdo->prepare('UPDATE sale_listings SET platform = ?, listing_price = ?, status = ?, listed_date = ?, buyer_name = ?, buyer_contact = ?, offer_amount = ?, deposit_amount = ?, notes = ? WHERE id = ?');
$stmt->execute([post_string('platform'), post_money('listing_price'), $status, post_date_or_null('listed_date'), post_string('buyer_name'), post_string('buyer_contact'), post_money('offer_amount'), post_money('deposit_amount'), post_string('notes'), $id]);
header('Location: ../pages/car-detail.php?id=' . (int) $carId);
exit;
?>

<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_login();
$carId = post_int('car_id', true);
require_car($pdo, $carId);
$status = require_allowed_value(post_string('status', true), ['Draft','Listed','Offer Received','Deposit Taken','Sold','Withdrawn'], 'status');
$stmt = $pdo->prepare('INSERT INTO sale_listings (car_id, platform, listing_price, status, listed_date, buyer_name, buyer_contact, offer_amount, deposit_amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$carId, post_string('platform'), post_money('listing_price'), $status, post_date_or_null('listed_date'), post_string('buyer_name'), post_string('buyer_contact'), post_money('offer_amount'), post_money('deposit_amount'), post_string('notes')]);
header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

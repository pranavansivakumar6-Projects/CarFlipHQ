<?php
require '../config/db.php';
require '../config/auth.php';
require_once '../config/status.php';
require_once '../config/helpers.php';

require_login();

$carId = post_int('id', true);
require_car($pdo, $carId);

$stmt = $pdo->prepare('SELECT * FROM cars WHERE id = ?');
$stmt->execute([$carId]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);

$partStmt = $pdo->prepare('SELECT * FROM parts WHERE car_id = ?');
$partStmt->execute([$carId]);
$parts = $partStmt->fetchAll(PDO::FETCH_ASSOC);

$taskStmt = $pdo->prepare('SELECT * FROM tasks WHERE car_id = ?');
$taskStmt->execute([$carId]);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

$listingStmt = $pdo->prepare('SELECT * FROM sale_listings WHERE car_id = ?');
$listingStmt->execute([$carId]);
$listings = $listingStmt->fetchAll(PDO::FETCH_ASSOC);

$postedStatus = normalise_car_status(post_string('status'));
$status = $postedStatus !== ''
    ? require_allowed_value($postedStatus, allowed_car_statuses(), 'status')
    : infer_car_status($car, $parts, $tasks, $listings);
$stmt = $pdo->prepare('UPDATE cars SET status = ? WHERE id = ?');
$stmt->execute([$status, $carId]);

redirect_to('pages/car-detail.php?id=' . $carId . '&status_synced=1');
?>

<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_admin();

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT id, profile_photo FROM cars WHERE id = ?');
$stmt->execute([$id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) {
    http_response_code(404);
    die('Car not found.');
}

$paths = [];
if (!empty($car['profile_photo'])) {
    $paths[] = (string) $car['profile_photo'];
}

$fileStmt = $pdo->prepare('SELECT file_path FROM car_files WHERE car_id = ?');
$fileStmt->execute([$id]);
$paths = array_merge($paths, array_map('strval', $fileStmt->fetchAll(PDO::FETCH_COLUMN)));

$expenseStmt = $pdo->prepare('SELECT receipt_file FROM expenses WHERE car_id = ? AND receipt_file IS NOT NULL AND receipt_file <> ""');
$expenseStmt->execute([$id]);
$paths = array_merge($paths, array_map('strval', $expenseStmt->fetchAll(PDO::FETCH_COLUMN)));

$taskStmt = $pdo->prepare('SELECT task_photo FROM tasks WHERE car_id = ? AND task_photo IS NOT NULL AND task_photo <> ""');
$taskStmt->execute([$id]);
$paths = array_merge($paths, array_map('strval', $taskStmt->fetchAll(PDO::FETCH_COLUMN)));

$deleteStmt = $pdo->prepare('DELETE FROM cars WHERE id = ?');
$deleteStmt->execute([$id]);

foreach (array_unique(array_filter($paths)) as $path) {
    delete_uploaded_file($path);
}

redirect_to('pages/cars.php?deleted=1');
?>

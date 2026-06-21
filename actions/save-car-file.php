<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$carId = post_int('car_id', true);
require_car($pdo, $carId);
$fileType = require_allowed_value(post_string('file_type', true), ['photo','document'], 'file_type');
$filePath = save_uploaded_file('car_file', 'cars');
if (!$filePath) { http_response_code(400); die('File is required.'); }

$stmt = $pdo->prepare('INSERT INTO car_files (car_id, file_type, title, file_path, notes) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$carId, $fileType, post_string('title', true), $filePath, post_string('notes')]);

header('Location: ../pages/car-detail.php?id=' . $carId);
exit;
?>

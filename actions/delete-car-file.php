<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT car_id, file_path FROM car_files WHERE id = ?');
$stmt->execute([$id]);
$file = $stmt->fetch();
if (!$file) { http_response_code(404); die('File not found.'); }

$stmt = $pdo->prepare('DELETE FROM car_files WHERE id = ?');
$stmt->execute([$id]);
delete_uploaded_file($file['file_path']);

header('Location: ../pages/car-detail.php?id=' . (int) $file['car_id']);
exit;
?>

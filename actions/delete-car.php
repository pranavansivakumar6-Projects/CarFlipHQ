<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_admin();

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT id FROM cars WHERE id = ?');
$stmt->execute([$id]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    die('Car not found.');
}

$stmt = $pdo->prepare('DELETE FROM cars WHERE id = ?');
$stmt->execute([$id]);

redirect_to('pages/cars.php?deleted=1');
?>

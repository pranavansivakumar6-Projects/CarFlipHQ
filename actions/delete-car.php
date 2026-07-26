<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_admin();

$id = post_int('id', true);
$stmt = $pdo->prepare('SELECT id FROM cars WHERE id = ? AND archived_at IS NULL');
$stmt->execute([$id]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    die('Car not found.');
}

$stmt = $pdo->prepare('UPDATE cars SET archived_at = NOW(), archived_by = ? WHERE id = ?');
$stmt->execute([(int) current_user()['id'], $id]);

redirect_to('pages/cars.php?archived=1');
?>

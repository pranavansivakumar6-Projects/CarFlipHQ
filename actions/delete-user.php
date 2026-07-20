<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_admin();

$id = post_int('id', true);
$current = current_user();
if ($current && (int) $current['id'] === $id) {
    redirect_to('pages/users.php?delete_error=self');
}

$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
$stmt->execute([$id]);
$role = $stmt->fetchColumn();
if (!$role) {
    redirect_to('pages/users.php?delete_error=missing');
}

if ($role === 'admin') {
    $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount <= 1) {
        redirect_to('pages/users.php?delete_error=last_admin');
    }
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$id]);

redirect_to('pages/users.php?deleted=1');
?>

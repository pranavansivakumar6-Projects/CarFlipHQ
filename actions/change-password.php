<?php
require '../config/db.php';
require '../config/auth.php';

require_login();

$user = current_user();
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
    redirect_to('pages/account.php?error=1');
}

$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([(int) $user['id']]);
$passwordHash = $stmt->fetchColumn();

if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
    redirect_to('pages/account.php?error=1');
}

$stmt = $pdo->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?');
$stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]);

$stmt = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
$stmt->execute([(int) $user['id']]);
$_SESSION['user']['session_version'] = (int) $stmt->fetchColumn();

redirect_to('pages/account.php?changed=1');
?>

<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_admin();

$id = post_int('id', true);
$name = post_string('name', true);
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$role = require_allowed_value(post_string('role', true), ['admin','partner'], 'role');

if (!$email || ($password !== '' && strlen($password) < 8)) {
    redirect_to('pages/edit-user.php?id=' . $id . '&error=1');
}

try {
    if ($password !== '') {
        $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, password_hash = ?, role = ?, session_version = session_version + 1 WHERE id = ?');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?');
        $stmt->execute([$name, $email, $role, $id]);
    }
} catch (PDOException $e) {
    redirect_to('pages/edit-user.php?id=' . $id . '&error=1');
}

$current = current_user();
if ($current && (int) $current['id'] === $id) {
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['email'] = $email;
    $_SESSION['user']['role'] = $role;
    if ($password !== '') {
        $_SESSION['user']['session_version'] = (int) ($_SESSION['user']['session_version'] ?? 0) + 1;
    }
}

redirect_to('pages/users.php');
?>

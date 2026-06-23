<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_admin();

$name = post_string('name', true);
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$role = require_allowed_value(post_string('role', true), ['admin','partner'], 'role');

if (!$email || strlen($password) < 8) {
    redirect_to('pages/add-user.php?error=1');
}

try {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
} catch (PDOException $e) {
    redirect_to('pages/add-user.php?error=1');
}

redirect_to('pages/users.php');
?>

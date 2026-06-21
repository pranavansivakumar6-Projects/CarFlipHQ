<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

if (user_count($pdo) > 0) {
    redirect_to('pages/login.php');
}

$name = post_string('name', true);
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || strlen($password) < 8) {
    redirect_to('pages/setup-admin.php?error=1');
}

$stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
$stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);

redirect_to('pages/login.php?created=1');
?>

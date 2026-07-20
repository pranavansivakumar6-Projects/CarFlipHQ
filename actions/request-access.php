<?php
require '../config/db.php';
require '../config/auth.php';

require_login();

$user = current_user();
$stmt = $pdo->prepare('UPDATE users SET access_requested_at = COALESCE(access_requested_at, NOW()) WHERE id = ?');
$stmt->execute([(int) $user['id']]);
$_SESSION['user']['access_requested_at'] = $_SESSION['user']['access_requested_at'] ?? date('Y-m-d H:i:s');

redirect_to('pages/account.php?requested=1');
?>

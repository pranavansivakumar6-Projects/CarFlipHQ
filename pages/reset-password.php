<?php
require '../config/db.php';
require '../config/auth.php';

redirect_if_logged_in();
$token = trim($_GET['token'] ?? '');
$pageTitle = 'Reset Password | CarFlip HQ';
$publicPage = true;
require '../header.php';
?>
<div class="auth-page">
    <form class="form-card auth-card" action="../actions/reset-password.php" method="POST">
        <h1>Choose new password</h1>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert">Reset link is invalid/expired, or passwords did not match.</div>
        <?php endif; ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <label>New Password</label><input name="password" type="password" autocomplete="new-password" minlength="8" required>
        <label>Confirm Password</label><input name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
        <br><br>
        <button class="btn" type="submit">Reset Password</button>
        <p class="small"><a href="login.php">Back to sign in</a></p>
    </form>
</div>
<?php require '../footer.php'; ?>

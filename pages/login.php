<?php
require '../config/db.php';
require '../config/auth.php';

if (user_count($pdo) === 0) {
    redirect_to('pages/setup-admin.php');
}

redirect_if_logged_in();
$pageTitle = 'Login | CarFlip HQ';
$publicPage = true;
require '../header.php';
?>
<div class="auth-page">
    <form class="form-card auth-card" action="../actions/login.php" method="POST">
        <h1>Sign in</h1>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert">Invalid email or password.</div>
        <?php endif; ?>
        <?php if (isset($_GET['created'])): ?>
            <div class="alert success">Admin account created. Sign in to continue.</div>
        <?php endif; ?>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert success">Account created. Sign in with your own email and password.</div>
        <?php endif; ?>
        <?php if (isset($_GET['reset'])): ?>
            <div class="alert success">Password reset. Sign in with your new password.</div>
        <?php endif; ?>
        <?php if (isset($_GET['changed'])): ?>
            <div class="alert">Your password changed. Sign in again with the new password.</div>
        <?php endif; ?>
        <label>Email</label><input name="email" type="email" autocomplete="email" required>
        <label>Password</label><input name="password" type="password" autocomplete="current-password" required>
        <br><br><button class="btn" type="submit">Sign in</button>
        <div class="auth-links">
            <a href="register.php">Create account</a>
            <a href="forgot-password.php">Forgot password?</a>
        </div>
    </form>
</div>
<?php require '../footer.php'; ?>

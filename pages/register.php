<?php
require '../config/db.php';
require '../config/auth.php';

if (user_count($pdo) === 0) {
    redirect_to('pages/setup-admin.php');
}

redirect_if_logged_in();
$pageTitle = 'Create Account | CarFlip HQ';
$publicPage = true;
require '../header.php';
?>
<div class="auth-page">
    <form class="form-card auth-card" action="../actions/register.php" method="POST">
        <h1>Create account</h1>
        <p class="small">Use your own login so nobody has to share the admin password.</p>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert">Check the details. Password must be at least 8 characters and email must be unique.</div>
        <?php endif; ?>
        <label>Name</label><input name="name" autocomplete="name" required>
        <label>Email</label><input name="email" type="email" autocomplete="email" required>
        <label>Password</label><input name="password" type="password" autocomplete="new-password" minlength="8" required>
        <label>Confirm Password</label><input name="confirm_password" type="password" autocomplete="new-password" minlength="8" required>
        <br><br>
        <button class="btn" type="submit">Create Account</button>
        <p class="small"><a href="login.php">Already have an account? Sign in</a></p>
    </form>
</div>
<?php require '../footer.php'; ?>

<?php
require '../config/db.php';
require '../config/auth.php';

require_admin();

$pageTitle = 'Add User | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Add User</h1>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert">Name, valid email, and a password of at least 8 characters are required.</div>
    <?php endif; ?>
    <form class="form-card" action="../actions/save-user.php" method="POST">
        <label>Name</label><input name="name" autocomplete="name" required>
        <label>Email</label><input name="email" type="email" autocomplete="email" required>
        <label>Password</label><input name="password" type="password" autocomplete="new-password" minlength="8" required>
        <label>Role</label>
        <select name="role">
            <option value="partner">Partner</option>
            <option value="admin">Admin</option>
        </select>
        <h2>Permissions</h2>
        <p class="small">Admins always get full access. For partners, tick only what this person should be able to do.</p>
        <div class="permission-grid">
            <?php foreach (permission_fields() as $key => $label): ?>
            <label class="check-pill"><input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($key) ?>"> <?= htmlspecialchars($label) ?></label>
            <?php endforeach; ?>
        </div>
        <br><br><button class="btn" type="submit">Save User</button>
        <a class="btn secondary" href="users.php">Cancel</a>
    </form>
</div>
<?php require '../footer.php'; ?>

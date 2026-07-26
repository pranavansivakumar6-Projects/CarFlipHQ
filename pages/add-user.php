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
        <select name="role" data-role-select>
            <option value="partner">User</option>
            <option value="admin">Admin</option>
        </select>
        <h2>Permissions</h2>
        <p class="small">Admins always get full access. Choose a preset, then adjust individual permissions if needed.</p>
        <label>Access preset</label>
        <select data-permission-preset data-default-preset="japan">
            <option value="custom">Custom permissions</option>
            <option value="japan" selected>Japan Hub only</option>
            <option value="japan_finance">Japan Hub with import numbers</option>
            <option value="full_no_numbers">Full CarFlip without financial numbers</option>
            <option value="full">Full CarFlip access</option>
        </select>
        <div class="permission-grid">
            <?php foreach (permission_fields() as $key => $label): ?>
            <label class="check-pill"><input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($key) ?>"> <?= htmlspecialchars($label) ?></label>
            <?php endforeach; ?>
        </div>
        <br><br><button class="btn" type="submit">Save User</button>
        <a class="btn secondary" href="users.php">Cancel</a>
    </form>
</div>
<script src="../assets/js/permissions.js"></script>
<?php require '../footer.php'; ?>

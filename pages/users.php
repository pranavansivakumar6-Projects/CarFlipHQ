<?php
require '../config/db.php';
require '../config/auth.php';

require_admin();

$users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'Users | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1>Users</h1>
    <p><a class="btn" href="add-user.php">+ Add User</a></p>
    <table>
        <tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Action</th></tr>
        <?php foreach ($users as $account): ?>
        <tr>
            <td><?= htmlspecialchars($account['name']) ?></td>
            <td><?= htmlspecialchars($account['email']) ?></td>
            <td><span class="badge"><?= htmlspecialchars($account['role']) ?></span></td>
            <td><?= htmlspecialchars($account['created_at']) ?></td>
            <td><a class="btn secondary small-btn" href="edit-user.php?id=<?= (int) $account['id'] ?>">Edit</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require '../footer.php'; ?>

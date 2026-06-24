<?php
require '../config/db.php';
require '../config/auth.php';
require_admin();

$pageTitle = 'Restore Backup | CarFlip HQ';
require '../header.php';
?>
<main class="container">
    <h1>Restore Backup</h1>
    <p class="small">Upload a CarFlip HQ SQL backup to restore the live database. This replaces the current live tables with the backup data.</p>

    <?php if (isset($_GET['restored'])): ?>
        <div class="alert success">Backup restored. Cars: <?= (int) ($_GET['cars'] ?? 0) ?>, expenses: <?= (int) ($_GET['expenses'] ?? 0) ?>, tasks: <?= (int) ($_GET['tasks'] ?? 0) ?>.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert">Restore failed: <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <form class="form-card" action="../actions/restore-backup.php" method="POST" enctype="multipart/form-data" onsubmit="return confirm('Restore this backup to the live database?');">
        <label>SQL backup file</label>
        <input name="backup_file" type="file" accept=".sql,text/plain" required>
        <br><br>
        <button class="btn" type="submit">Restore Backup</button>
    </form>
</main>
<?php require '../footer.php'; ?>

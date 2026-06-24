<?php
require '../config/db.php';
$pageTitle = 'Cars | CarFlip HQ';
require '../header.php';
$cars = $pdo->query("SELECT * FROM cars ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$isAdmin = (($user['role'] ?? '') === 'admin');
?>
<div class="container">
    <h1>Cars</h1>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert success">Car deleted.</div>
    <?php endif; ?>
    <?php if (isset($_GET['deduped'])): ?>
        <div class="alert success">Removed <?= (int) $_GET['deduped'] ?> duplicate cars.</div>
    <?php endif; ?>
    <div class="actions">
        <a class="btn" href="add-car.php">+ Add Car</a>
        <a class="btn secondary" href="import-sheet.php">Import Sheet</a>
        <?php if ($isAdmin): ?>
        <form method="post" action="<?= app_url('actions/delete-duplicate-cars.php') ?>" onsubmit="return confirm('Remove exact duplicate car rows and keep the first copy?');">
            <button class="btn danger" type="submit">Clean Duplicates</button>
        </form>
        <?php endif; ?>
    </div>
    <table>
        <tr><th>Car</th><th>Rego</th><th>Odometer</th><th>Status</th><th>Purchase</th><th>Action</th></tr>
        <?php foreach ($cars as $car): ?>
        <tr>
            <td><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></td>
            <td><?= htmlspecialchars((string) ($car['rego'] ?: 'N/A')) ?></td>
            <td><?= number_format((int)$car['odometer']) ?> km</td>
            <td><span class="badge"><?= htmlspecialchars((string) $car['status']) ?></span></td>
            <td>$<?= number_format($car['purchase_price'], 2) ?></td>
            <td>
                <div class="row-actions">
                    <a class="btn secondary" href="car-detail.php?id=<?= $car['id'] ?>">Open</a>
                    <?php if ($isAdmin): ?>
                    <form method="post" action="<?= app_url('actions/delete-car.php') ?>" onsubmit="return confirm('Delete this car and all its expenses, tasks, files, parts, and purchase payments?');">
                        <input type="hidden" name="id" value="<?= (int) $car['id'] ?>">
                        <button class="btn danger" type="submit">Delete</button>
                    </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require '../footer.php'; ?>

<?php
require '../config/db.php';
require_once '../config/status.php';
$pageTitle = 'Cars | CarFlip HQ';
require '../header.php';
$cars = $pdo->query("
    SELECT c.*,
        (SELECT file_path FROM car_files cf WHERE cf.car_id = c.id ORDER BY cf.created_at DESC LIMIT 1) AS photo_path,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses e WHERE e.car_id = c.id) AS expense_total
    FROM cars c
    ORDER BY c.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
$isAdmin = (($user['role'] ?? '') === 'admin');

function car_status_class(?string $status): string
{
    return 'status-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $status)), '-');
}
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

    <div class="car-card-grid section-title">
        <?php foreach ($cars as $car): ?>
        <?php
        $saleValue = (float) ($car['actual_sale_price'] ?: $car['estimated_sale_price']);
        $totalCost = (float) $car['purchase_price'] + (float) $car['expense_total'];
        $profit = $saleValue > 0 ? $saleValue - $totalCost : null;
        ?>
        <article class="car-card">
            <a class="car-card-media" href="car-detail.php?id=<?= (int) $car['id'] ?>">
                <?php if (!empty($car['photo_path'])): ?>
                    <img src="../<?= htmlspecialchars($car['photo_path']) ?>" alt="<?= htmlspecialchars(trim($car['year'] . ' ' . $car['make'] . ' ' . $car['model'])) ?>">
                <?php else: ?>
                    <span><?= htmlspecialchars(substr((string) ($car['make'] ?: 'Car'), 0, 1)) ?></span>
                <?php endif; ?>
            </a>
            <div class="car-card-body">
                <div class="card-title-row">
                    <h2><?= htmlspecialchars(trim($car['year'] . ' ' . $car['make'] . ' ' . $car['model'])) ?></h2>
                    <span class="badge <?= car_status_class($car['status'] ?? '') ?>"><?= htmlspecialchars(car_status_label((string) $car['status'])) ?></span>
                </div>
                <div class="car-metrics">
                    <div><span>Purchase</span><b>$<?= number_format((float) $car['purchase_price'], 2) ?></b></div>
                    <div><span>Expenses</span><b>$<?= number_format((float) $car['expense_total'], 2) ?></b></div>
                    <div><span><?= (float) $car['actual_sale_price'] > 0 ? 'Sold' : 'Est. Sale' ?></span><b>$<?= number_format($saleValue, 2) ?></b></div>
                    <div><span>Profit</span><b class="<?= $profit === null ? '' : ($profit >= 0 ? 'positive' : 'negative') ?>"><?= $profit === null ? 'N/A' : '$' . number_format($profit, 2) ?></b></div>
                </div>
                <div class="card-title-row">
                    <span class="small"><?= number_format((int) $car['odometer']) ?> km<?= $car['rego'] ? ' / ' . htmlspecialchars((string) $car['rego']) : '' ?></span>
                    <a class="btn secondary small-btn" href="car-detail.php?id=<?= (int) $car['id'] ?>">Open</a>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <table>
        <tr><th>Car</th><th>Rego</th><th>Odometer</th><th>Status</th><th>Purchase</th><th>Action</th></tr>
        <?php foreach ($cars as $car): ?>
        <tr>
            <td><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></td>
            <td><?= htmlspecialchars((string) ($car['rego'] ?: 'N/A')) ?></td>
            <td><?= number_format((int)$car['odometer']) ?> km</td>
            <td><span class="badge"><?= htmlspecialchars(car_status_label((string) $car['status'])) ?></span></td>
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

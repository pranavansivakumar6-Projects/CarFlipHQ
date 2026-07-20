<?php
require_once dirname(__DIR__, 2) . '/config/status.php';
if (!isset($recentCars)) {
    require_once dirname(__DIR__, 2) . '/config/helpers.php';
    $accessWhere = car_access_filter_sql('cars');
    $cars = $pdo->query("SELECT * FROM cars WHERE $accessWhere ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $cars = $recentCars;
}
$canViewFinance = $canViewFinance ?? user_can('can_view_finance');
?>
<table>
    <tr>
        <th>Car</th>
        <th>Status</th>
        <?php if ($canViewFinance): ?>
        <th>Purchase</th><th>Estimated Sale</th><th>Sold Price</th>
        <?php endif; ?>
        <th>Action</th>
    </tr>
    <?php foreach ($cars as $car): ?>
    <tr>
        <td><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></td>
        <td><span class="badge"><?= htmlspecialchars(car_status_label((string) $car['status'])) ?></span></td>
        <?php if ($canViewFinance): ?>
        <td>$<?= number_format($car['purchase_price'], 2) ?></td>
        <td>$<?= number_format($car['estimated_sale_price'], 2) ?></td>
        <td><?= (float) $car['actual_sale_price'] > 0 ? '$'.number_format($car['actual_sale_price'], 2) : '-' ?></td>
        <?php endif; ?>
        <td><a class="btn secondary" href="<?= app_url('pages/car-detail.php?id=' . (int) $car['id']) ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
</table>

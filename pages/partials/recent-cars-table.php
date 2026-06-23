<?php
$cars = $recentCars ?? $pdo->query("SELECT * FROM cars ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
?>
<table>
    <tr><th>Car</th><th>Status</th><th>Purchase</th><th>Estimated Sale</th><th>Sold Price</th><th>Action</th></tr>
    <?php foreach ($cars as $car): ?>
    <tr>
        <td><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></td>
        <td><span class="badge"><?= htmlspecialchars($car['status']) ?></span></td>
        <td>$<?= number_format($car['purchase_price'], 2) ?></td>
        <td>$<?= number_format($car['estimated_sale_price'], 2) ?></td>
        <td><?= (float) $car['actual_sale_price'] > 0 ? '$'.number_format($car['actual_sale_price'], 2) : '-' ?></td>
        <td><a class="btn secondary" href="<?= app_url('pages/car-detail.php?id=' . (int) $car['id']) ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
</table>

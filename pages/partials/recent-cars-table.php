<?php
$cars = $recentCars ?? $pdo->query("SELECT * FROM cars ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
?>
<table>
    <tr><th>Car</th><th>Status</th><th>Purchase</th><th>Estimated Sale</th><th>Action</th></tr>
    <?php foreach ($cars as $car): ?>
    <tr>
        <td><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></td>
        <td><span class="badge"><?= htmlspecialchars($car['status']) ?></span></td>
        <td>$<?= number_format($car['purchase_price'], 2) ?></td>
        <td>$<?= number_format($car['estimated_sale_price'], 2) ?></td>
        <td><a class="btn secondary" href="/carfliphq/pages/car-detail.php?id=<?= $car['id'] ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
</table>

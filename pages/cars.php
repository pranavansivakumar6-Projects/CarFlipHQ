<?php
require '../config/db.php';
$pageTitle = 'Cars | CarFlip HQ';
require '../header.php';
$cars = $pdo->query("SELECT * FROM cars ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container">
    <h1>Cars</h1>
    <p><a class="btn" href="add-car.php">+ Add Car</a></p>
    <table>
        <tr><th>Car</th><th>Rego</th><th>Odometer</th><th>Status</th><th>Purchase</th><th>Action</th></tr>
        <?php foreach ($cars as $car): ?>
        <tr>
            <td><?= htmlspecialchars($car['year'].' '.$car['make'].' '.$car['model']) ?></td>
            <td><?= htmlspecialchars($car['rego']) ?></td>
            <td><?= number_format((int)$car['odometer']) ?> km</td>
            <td><span class="badge"><?= htmlspecialchars($car['status']) ?></span></td>
            <td>$<?= number_format($car['purchase_price'], 2) ?></td>
            <td><a class="btn secondary" href="car-detail.php?id=<?= $car['id'] ?>">Open</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require '../footer.php'; ?>

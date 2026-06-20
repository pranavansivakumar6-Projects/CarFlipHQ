<?php
require 'config/db.php';
$pageTitle = 'Dashboard | CarFlip HQ';
require 'header.php';

$totalCars = $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
$activeCars = $pdo->query("SELECT COUNT(*) FROM cars WHERE status != 'Sold'")->fetchColumn();
$soldCars = $pdo->query("SELECT COUNT(*) FROM cars WHERE status = 'Sold'")->fetchColumn();
$openTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'Done'")->fetchColumn();
$totalPurchase = $pdo->query("SELECT COALESCE(SUM(purchase_price),0) FROM cars")->fetchColumn();
$totalExpenses = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();
?>
<div class="container">
    <h1>Dashboard</h1>
    <div class="grid">
        <div class="card"><div>Total Cars</div><div class="stat"><?= $totalCars ?></div></div>
        <div class="card"><div>Active Cars</div><div class="stat"><?= $activeCars ?></div></div>
        <div class="card"><div>Sold Cars</div><div class="stat"><?= $soldCars ?></div></div>
        <div class="card"><div>Open Tasks</div><div class="stat"><?= $openTasks ?></div></div>
        <div class="card"><div>Total Purchase Cost</div><div class="stat">$<?= number_format($totalPurchase, 2) ?></div></div>
        <div class="card"><div>Total Extra Expenses</div><div class="stat">$<?= number_format($totalExpenses, 2) ?></div></div>
    </div>
    <h2 class="section-title">Recent Cars</h2>
    <p><a class="btn" href="pages/add-car.php">+ Add New Car</a></p>
    <?php include 'pages/partials/recent-cars-table.php'; ?>
</div>
<?php require 'footer.php'; ?>

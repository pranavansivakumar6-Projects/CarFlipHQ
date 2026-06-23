<?php
require '../config/db.php';
$pageTitle = 'Reports | CarFlip HQ';
require '../header.php';
$totalCars = (int) $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
$activeCars = (int) $pdo->query("SELECT COUNT(*) FROM cars WHERE status != 'Sold'")->fetchColumn();
$soldCars = (int) $pdo->query("SELECT COUNT(*) FROM cars WHERE status = 'Sold'")->fetchColumn();
$purchaseTotal = (float) $pdo->query("SELECT COALESCE(SUM(purchase_price),0) FROM cars")->fetchColumn();
$expenseTotal = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();
$actualSales = (float) $pdo->query("SELECT COALESCE(SUM(actual_sale_price),0) FROM cars WHERE actual_sale_price > 0")->fetchColumn();
$estimatedSales = (float) $pdo->query("SELECT COALESCE(SUM(estimated_sale_price),0) FROM cars")->fetchColumn();
$hours = (float) $pdo->query("SELECT COALESCE(SUM(hours_spent),0) FROM tasks")->fetchColumn();
$overdueTasks = (int) $pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'Done' AND due_date IS NOT NULL AND due_date < CURDATE()")->fetchColumn();
$payerRows = $pdo->query("SELECT paid_by, SUM(amount) AS total FROM expenses WHERE paid_by IS NOT NULL AND paid_by != '' GROUP BY paid_by ORDER BY paid_by")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container">
    <h1>Reports</h1>
    <div class="grid">
        <div class="card"><b>Total Cars</b><div class="stat"><?= $totalCars ?></div></div>
        <div class="card"><b>Active Cars</b><div class="stat"><?= $activeCars ?></div></div>
        <div class="card"><b>Sold Cars</b><div class="stat"><?= $soldCars ?></div></div>
        <div class="card"><b>Cash In Cars</b><div class="stat">$<?= number_format($purchaseTotal + $expenseTotal, 2) ?></div></div>
        <div class="card"><b>Actual Sales</b><div class="stat">$<?= number_format($actualSales, 2) ?></div></div>
        <div class="card"><b>Expected Sales</b><div class="stat">$<?= number_format($estimatedSales, 2) ?></div></div>
        <div class="card"><b>Task Hours</b><div class="stat"><?= number_format($hours, 2) ?></div></div>
        <div class="card"><b>Overdue Tasks</b><div class="stat"><?= $overdueTasks ?></div></div>
    </div>
    <h2 class="section-title">Expenses Paid By</h2>
    <table><tr><th>Person</th><th>Total Paid</th></tr>
    <?php foreach ($payerRows as $row): ?><tr><td><?= htmlspecialchars($row['paid_by']) ?></td><td>$<?= number_format($row['total'], 2) ?></td></tr><?php endforeach; ?>
    </table>
</div>
<?php require '../footer.php'; ?>

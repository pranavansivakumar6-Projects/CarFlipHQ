<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_view_data');
require_once '../config/helpers.php';
$pageTitle = 'Reports | CarFlip HQ';
require '../header.php';
$carsAccessWhere = car_access_filter_sql('cars');
$carJoinAccessWhere = car_access_filter_sql('c');
$totalCars = (int) $pdo->query("SELECT COUNT(*) FROM cars WHERE $carsAccessWhere")->fetchColumn();
$activeCars = (int) $pdo->query("SELECT COUNT(*) FROM cars WHERE status != 'Sold' AND $carsAccessWhere")->fetchColumn();
$soldCars = (int) $pdo->query("SELECT COUNT(*) FROM cars WHERE status = 'Sold' AND $carsAccessWhere")->fetchColumn();
$purchaseTotal = (float) $pdo->query("SELECT COALESCE(SUM(purchase_price),0) FROM cars WHERE $carsAccessWhere")->fetchColumn();
$expenseTotal = (float) $pdo->query("SELECT COALESCE(SUM(e.amount),0) FROM expenses e JOIN cars c ON c.id = e.car_id WHERE $carJoinAccessWhere")->fetchColumn();
$actualSales = (float) $pdo->query("SELECT COALESCE(SUM(actual_sale_price),0) FROM cars WHERE actual_sale_price > 0 AND $carsAccessWhere")->fetchColumn();
$estimatedSales = (float) $pdo->query("SELECT COALESCE(SUM(estimated_sale_price),0) FROM cars WHERE $carsAccessWhere")->fetchColumn();
$hours = (float) $pdo->query("SELECT COALESCE(SUM(t.hours_spent),0) FROM tasks t JOIN cars c ON c.id = t.car_id WHERE $carJoinAccessWhere")->fetchColumn();
$overdueTasks = (int) $pdo->query("SELECT COUNT(*) FROM tasks t JOIN cars c ON c.id = t.car_id WHERE t.status != 'Done' AND t.due_date IS NOT NULL AND t.due_date < CURDATE() AND $carJoinAccessWhere")->fetchColumn();
$payerRows = $pdo->query("SELECT e.paid_by, SUM(e.amount) AS total FROM expenses e JOIN cars c ON c.id = e.car_id WHERE e.paid_by IS NOT NULL AND e.paid_by != '' AND $carJoinAccessWhere GROUP BY e.paid_by ORDER BY e.paid_by")->fetchAll(PDO::FETCH_ASSOC);
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

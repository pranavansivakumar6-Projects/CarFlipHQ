<?php
require 'config/db.php';
require_once 'config/auth.php';
require_permission('can_view_data');
require_once 'config/helpers.php';
$pageTitle = 'Dashboard | CarFlip HQ';
require 'header.php';

$scope = $_GET['scope'] ?? 'all';
if (!in_array($scope, ['active', 'sold', 'all'], true)) { $scope = 'all'; }

$scopeWhere = [
    'active' => "status != 'Sold' AND archived_at IS NULL",
    'sold' => "status = 'Sold' AND archived_at IS NULL",
    'all' => 'archived_at IS NULL',
][$scope];
$expenseScopeWhere = [
    'active' => "c.status != 'Sold' AND c.archived_at IS NULL",
    'sold' => "c.status = 'Sold' AND c.archived_at IS NULL",
    'all' => 'c.archived_at IS NULL',
][$scope];
$taskScopeWhere = [
    'active' => "cars.status != 'Sold' AND cars.archived_at IS NULL",
    'sold' => "cars.status = 'Sold' AND cars.archived_at IS NULL",
    'all' => 'cars.archived_at IS NULL',
][$scope];
$salesSql = $scope === 'active'
    ? 'COALESCE(SUM(estimated_sale_price),0)'
    : 'COALESCE(SUM(CASE WHEN actual_sale_price > 0 THEN actual_sale_price ELSE estimated_sale_price END),0)';
$scopeLabel = ['active' => 'Active Cars', 'sold' => 'Sold Cars', 'all' => 'All Cars'][$scope];
$profitLabel = $scope === 'sold' ? 'Realized Profit' : ($scope === 'active' ? 'Expected Active Profit' : 'Projected/Realized Profit');
$canViewFinance = user_can('can_view_finance');
$carAccessWhere = car_access_filter_sql('cars');
$expenseAccessWhere = car_access_filter_sql('c');
$taskAccessWhere = car_access_filter_sql('cars');

$totalCars = $pdo->query("SELECT COUNT(*) FROM cars WHERE $scopeWhere AND $carAccessWhere")->fetchColumn();
$activeCars = $pdo->query("SELECT COUNT(*) FROM cars WHERE status != 'Sold' AND archived_at IS NULL AND $carAccessWhere")->fetchColumn();
$soldCars = $pdo->query("SELECT COUNT(*) FROM cars WHERE status = 'Sold' AND archived_at IS NULL AND $carAccessWhere")->fetchColumn();
$openTasks = $pdo->query("SELECT COUNT(*) FROM tasks JOIN cars ON cars.id = tasks.car_id WHERE tasks.status != 'Done' AND $taskScopeWhere AND $taskAccessWhere")->fetchColumn();
$overdueTasks = $pdo->query("SELECT COUNT(*) FROM tasks JOIN cars ON cars.id = tasks.car_id WHERE tasks.status != 'Done' AND tasks.due_date IS NOT NULL AND tasks.due_date < CURDATE() AND $taskScopeWhere AND $taskAccessWhere")->fetchColumn();
$readyCars = $pdo->query("SELECT COUNT(*) FROM cars WHERE status IN ('Ready for Sale','Listed') AND $scopeWhere AND $carAccessWhere")->fetchColumn();
$totalPurchase = (float) $pdo->query("SELECT COALESCE(SUM(purchase_price),0) FROM cars WHERE $scopeWhere AND $carAccessWhere")->fetchColumn();
$totalExpenses = (float) $pdo->query("SELECT COALESCE(SUM(e.amount),0) FROM expenses e JOIN cars c ON c.id = e.car_id WHERE $expenseScopeWhere AND $expenseAccessWhere")->fetchColumn();
$salesValue = (float) $pdo->query("SELECT $salesSql FROM cars WHERE $scopeWhere AND $carAccessWhere")->fetchColumn();
$expectedProfit = $salesValue - $totalPurchase - $totalExpenses;
$recentCars = $pdo->query("SELECT * FROM cars WHERE $scopeWhere AND $carAccessWhere ORDER BY created_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
$overdueTaskList = $pdo->query("
    SELECT tasks.task_title, tasks.due_date, cars.id AS car_id, cars.year, cars.make, cars.model
    FROM tasks
    JOIN cars ON cars.id = tasks.car_id
    WHERE tasks.status != 'Done'
      AND tasks.due_date IS NOT NULL
      AND tasks.due_date < CURDATE()
      AND $taskScopeWhere
      AND $taskAccessWhere
    ORDER BY tasks.due_date ASC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);
$missingSalePriceCars = $pdo->query("
    SELECT id, year, make, model
    FROM cars
    WHERE status != 'Sold'
      AND archived_at IS NULL
      AND COALESCE(estimated_sale_price, 0) = 0
      AND $carAccessWhere
    ORDER BY created_at DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC);
$missingSoldPriceCars = $canViewFinance ? $pdo->query("
    SELECT id, year, make, model
    FROM cars
    WHERE status = 'Sold'
      AND archived_at IS NULL
      AND COALESCE(actual_sale_price, 0) = 0
      AND $carAccessWhere
    ORDER BY created_at DESC
    LIMIT 4
")->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<div class="container dashboard-view">
    <div class="dashboard-hero">
        <div>
            <div class="eyebrow">CarFlip HQ Operations</div>
            <h1>Dashboard</h1>
            <p>Track cars, repair work, expenses, sale progress, and partner payouts from one place.</p>
            <div class="hero-stats">
                <div><span><?= htmlspecialchars($scopeLabel) ?></span><b><?= $totalCars ?></b></div>
                <div><span><?= htmlspecialchars($profitLabel) ?></span><b><?= $canViewFinance ? '$' . number_format($expectedProfit, 2) : 'Restricted' ?></b></div>
                <div><span>Ready/Listable</span><b><?= $readyCars ?></b></div>
            </div>
        </div>
        <form class="filter-bar" method="GET">
            <label for="scope">Summary</label>
            <select id="scope" name="scope" onchange="this.form.submit()">
                <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>All cars</option>
                <option value="active" <?= $scope === 'active' ? 'selected' : '' ?>>Active cars only</option>
                <option value="sold" <?= $scope === 'sold' ? 'selected' : '' ?>>Sold cars only</option>
            </select>
        </form>
    </div>
    <div class="grid stat-grid">
        <div class="card"><div><?= htmlspecialchars($scopeLabel) ?></div><div class="stat"><?= $totalCars ?></div></div>
        <div class="card"><div>Active Cars</div><div class="stat"><?= $activeCars ?></div></div>
        <div class="card"><div>Sold Cars</div><div class="stat"><?= $soldCars ?></div></div>
        <div class="card"><div>Open Tasks</div><div class="stat"><?= $openTasks ?></div></div>
        <div class="card"><div>Overdue Tasks</div><div class="stat"><?= $overdueTasks ?></div></div>
        <div class="card"><div>Ready/Listable</div><div class="stat"><?= $readyCars ?></div></div>
        <?php if ($canViewFinance): ?>
        <div class="card"><div>Purchase Cost</div><div class="stat">$<?= number_format($totalPurchase, 2) ?></div><div class="small"><?= htmlspecialchars($scopeLabel) ?></div></div>
        <div class="card"><div>Extra Expenses</div><div class="stat">$<?= number_format($totalExpenses, 2) ?></div><div class="small"><?= htmlspecialchars($scopeLabel) ?></div></div>
        <div class="card"><div><?= htmlspecialchars($profitLabel) ?></div><div class="profit <?= $expectedProfit >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($expectedProfit, 2) ?></div></div>
        <?php else: ?>
        <div class="card"><div>Financials</div><div class="stat restricted-stat">Restricted</div><div class="small">Ask an admin for number access.</div></div>
        <?php endif; ?>
    </div>
    <div class="work-queue">
        <div class="queue-card">
            <div>
                <span class="eyebrow">Attention</span>
                <h3>Overdue Tasks</h3>
            </div>
            <?php if ($overdueTaskList): ?>
                <?php foreach ($overdueTaskList as $task): ?>
                    <a href="pages/car-detail.php?id=<?= (int) $task['car_id'] ?>#tasks"><?= htmlspecialchars($task['task_title']) ?><span><?= htmlspecialchars($task['year'].' '.$task['make'].' '.$task['model']) ?> / <?= htmlspecialchars($task['due_date']) ?></span></a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="small">No overdue tasks for this view.</p>
            <?php endif; ?>
        </div>
        <div class="queue-card">
            <div>
                <span class="eyebrow">Setup</span>
                <h3>Missing Sale Estimates</h3>
            </div>
            <?php if ($missingSalePriceCars): ?>
                <?php foreach ($missingSalePriceCars as $missingCar): ?>
                    <a href="pages/edit-car.php?id=<?= (int) $missingCar['id'] ?>"><?= htmlspecialchars($missingCar['year'].' '.$missingCar['make'].' '.$missingCar['model']) ?><span>Add expected sale price</span></a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="small">Every active car has an expected sale price.</p>
            <?php endif; ?>
        </div>
        <?php if ($canViewFinance): ?>
        <div class="queue-card">
            <div>
                <span class="eyebrow">Sales</span>
                <h3>Sold Price Needed</h3>
            </div>
            <?php if ($missingSoldPriceCars): ?>
                <?php foreach ($missingSoldPriceCars as $soldCar): ?>
                    <a href="pages/edit-car.php?id=<?= (int) $soldCar['id'] ?>"><?= htmlspecialchars($soldCar['year'].' '.$soldCar['make'].' '.$soldCar['model']) ?><span>Add actual sold price</span></a>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="small">Sold cars have actual sale prices recorded.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="page-heading section-title">
        <div>
            <h2>Recent Cars</h2>
            <p class="small">Open a car to review tasks, purchase payments, expenses, files, and split details.</p>
        </div>
        <?php if (user_can('can_manage_cars')): ?>
        <a class="btn" href="pages/add-car.php">+ Add New Car</a>
        <?php endif; ?>
    </div>
    <?php include 'pages/partials/recent-cars-table.php'; ?>
</div>
<?php require 'footer.php'; ?>

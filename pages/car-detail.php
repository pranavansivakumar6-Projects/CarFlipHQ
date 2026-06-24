<?php
require '../config/db.php';

function detail_text($value, string $fallback = ''): string
{
    $text = trim((string) ($value ?? ''));
    return htmlspecialchars($text === '' ? $fallback : $text);
}

function detail_lines($value): string
{
    return nl2br(htmlspecialchars((string) ($value ?? '')));
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); die('Car ID missing'); }
$stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->execute([$id]);
$car = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$car) { http_response_code(404); die('Car not found'); }

$expenseStmt = $pdo->prepare("SELECT * FROM expenses WHERE car_id = ? ORDER BY expense_date DESC, created_at DESC");
$expenseStmt->execute([$id]);
$expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

$fileStmt = $pdo->prepare("SELECT * FROM car_files WHERE car_id = ? ORDER BY created_at DESC");
$fileStmt->execute([$id]);
$carFiles = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

$partStmt = $pdo->prepare("SELECT * FROM parts WHERE car_id = ? ORDER BY FIELD(status, 'Needed','Ordered','Arrived','Installed','Cancelled'), created_at DESC");
$partStmt->execute([$id]);
$parts = $partStmt->fetchAll(PDO::FETCH_ASSOC);

$listingStmt = $pdo->prepare("SELECT * FROM sale_listings WHERE car_id = ? ORDER BY listed_date DESC, created_at DESC");
$listingStmt->execute([$id]);
$listings = $listingStmt->fetchAll(PDO::FETCH_ASSOC);

$purchaseStmt = $pdo->prepare("SELECT * FROM car_purchase_payments WHERE car_id = ? ORDER BY paid_date DESC, created_at DESC");
$purchaseStmt->execute([$id]);
$purchasePayments = $purchaseStmt->fetchAll(PDO::FETCH_ASSOC);

$shareStmt = $pdo->prepare("SELECT person_name, share_percent FROM car_profit_shares WHERE car_id = ? ORDER BY person_name ASC");
$shareStmt->execute([$id]);
$savedShares = $shareStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$taskStmt = $pdo->prepare("SELECT * FROM tasks WHERE car_id = ? ORDER BY due_date ASC, created_at DESC");
$taskStmt->execute([$id]);
$tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT name FROM users ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$totalTaskHours = array_sum(array_map(fn($task) => (float) ($task['hours_spent'] ?? 0), $tasks));
$partsCost = array_sum(array_column($parts, 'cost'));
$openParts = count(array_filter($parts, fn($part) => !in_array($part['status'], ['Installed','Cancelled'], true)));
$totalExpenses = array_sum(array_column($expenses, 'amount'));
$totalCost = $car['purchase_price'] + $totalExpenses;
$estimatedProfit = $car['estimated_sale_price'] - $totalCost;
$actualProfit = $car['actual_sale_price'] > 0 ? $car['actual_sale_price'] - $totalCost : null;
$saleValue = $car['actual_sale_price'] > 0 ? (float) $car['actual_sale_price'] : (float) $car['estimated_sale_price'];
$profitForSplit = $saleValue - $totalCost;
$paidTotals = [];
foreach ($expenses as $expense) {
    if (!empty($expense['paid_by'])) {
        $paidTotals[$expense['paid_by']] = ($paidTotals[$expense['paid_by']] ?? 0) + (float) $expense['amount'];
    }
}
$purchasePaidTotal = array_sum(array_column($purchasePayments, 'amount'));
foreach ($purchasePayments as $payment) {
    if (!empty($payment['paid_by'])) {
        $paidTotals[$payment['paid_by']] = ($paidTotals[$payment['paid_by']] ?? 0) + (float) $payment['amount'];
    }
}
$taskParticipantNames = [];
foreach ($tasks as $task) {
    foreach (explode(',', (string) ($task['assigned_to'] ?? '')) as $assignedName) {
        $assignedName = trim($assignedName);
        if ($assignedName !== '') {
            $taskParticipantNames[] = $assignedName;
        }
    }
}
$partnerNames = array_values(array_unique(array_merge(array_keys($paidTotals), $taskParticipantNames, array_keys($savedShares))));
$partnerCount = max(count($partnerNames), 1);
$hasSavedShares = count($savedShares) > 0;
$sharePercents = [];
foreach ($partnerNames as $name) {
    $sharePercents[$name] = isset($savedShares[$name]) ? (float) $savedShares[$name] : null;
}
$shareTotal = array_sum(array_map(fn($share) => (float) ($share ?? 0), $sharePercents));
$unassignedExpenses = array_sum(array_map(fn($expense) => empty($expense['paid_by']) ? (float) $expense['amount'] : 0.0, $expenses));
$unassignedPurchase = max((float) $car['purchase_price'] - $purchasePaidTotal, 0);
$settlements = [];
$costShares = [];
$profitShares = [];
$salePayouts = [];
foreach ($partnerNames as $name) {
    $paidAmount = $paidTotals[$name] ?? 0.0;
    $sharePercent = $sharePercents[$name];
    $costShares[$name] = $sharePercent === null ? null : $totalCost * ($sharePercent / 100);
    $profitShares[$name] = $sharePercent === null ? null : $profitForSplit * ($sharePercent / 100);
    $settlements[$name] = $sharePercent === null ? null : $paidAmount - $costShares[$name];
    $salePayouts[$name] = $sharePercent === null ? null : $paidAmount + $profitShares[$name];
}
$statusSteps = ['Bought','Waiting for Parts','Under Repair','RWC Pending','Ready for Sale','Listed','Sold'];
$currentStatusIndex = array_search($car['status'], $statusSteps, true);
$currentStatusIndex = $currentStatusIndex === false ? 0 : $currentStatusIndex;
$tasksByStatus = [
    'To Do' => [],
    'In Progress' => [],
    'Done' => [],
];
foreach ($tasks as $task) {
    $taskStatus = $task['status'] ?? 'To Do';
    $tasksByStatus[$taskStatus][] = $task;
}
$pageTitle = $car['make'].' '.$car['model'].' | CarFlip HQ';
require '../header.php';
?>
<div class="container">
    <h1><?= detail_text($car['year'].' '.$car['make'].' '.$car['model']) ?></h1>
    <div class="actions">
        <a class="btn secondary" href="edit-car.php?id=<?= $id ?>">Edit Car</a>
        <a class="btn secondary" href="../actions/export-car-sheet.php?id=<?= $id ?>">Download Sheet</a>
        <a class="btn secondary" href="ai.php?car_id=<?= $id ?>">AI Tools</a>
    </div>

    <div class="grid section-title">
        <div class="card"><b>Status</b><div class="stat"><?= detail_text($car['status']) ?></div></div>
        <div class="card"><b>Total Cost</b><div class="stat">$<?= number_format($totalCost, 2) ?></div></div>
        <div class="card"><b>Task Hours</b><div class="stat"><?= number_format($totalTaskHours, 2) ?></div></div>
        <div class="card"><b>Open Parts</b><div class="stat"><?= $openParts ?></div><div class="small">$<?= number_format($partsCost, 2) ?> tracked</div></div>
        <div class="card"><b>Estimated Profit</b><div class="profit <?= $estimatedProfit >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($estimatedProfit, 2) ?></div></div>
        <div class="card"><b>Actual Profit</b><div class="profit <?= ($actualProfit ?? 0) >= 0 ? 'positive' : 'negative' ?>"><?= $actualProfit === null ? 'Not sold' : '$'.number_format($actualProfit, 2) ?></div></div>
    </div>

    <div class="timeline-card section-title">
        <?php foreach ($statusSteps as $stepIndex => $step): ?>
        <div class="timeline-step <?= $stepIndex < $currentStatusIndex ? 'complete' : ($stepIndex === $currentStatusIndex ? 'current' : '') ?>">
            <span></span>
            <b><?= detail_text($step) ?></b>
        </div>
        <?php endforeach; ?>
    </div>

    <h2 class="section-title">Finance Split</h2>
    <?php if (isset($_GET['shares'])): ?>
        <div class="alert success">Profit split updated.</div>
    <?php endif; ?>
    <div class="grid">
        <div class="card"><b>Sale Value Used</b><div class="stat">$<?= number_format($saleValue, 2) ?></div><div class="small"><?= $car['actual_sale_price'] > 0 ? 'Actual sale price' : 'Estimated sale price' ?></div></div>
        <div class="card"><b>Total Invested</b><div class="stat">$<?= number_format($totalCost, 2) ?></div><div class="small">$<?= number_format($car['purchase_price'], 2) ?> car + $<?= number_format($totalExpenses, 2) ?> expenses</div></div>
        <div class="card"><b>Total Profit</b><div class="profit <?= $profitForSplit >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($profitForSplit, 2) ?></div><div class="small">Sale minus total invested</div></div>
        <div class="card"><b>Profit Split</b><div class="profit <?= $hasSavedShares ? ($profitForSplit >= 0 ? 'positive' : 'negative') : '' ?>"><?= $hasSavedShares ? number_format($shareTotal, 2).'%' : 'Not set' ?></div><div class="small"><?= $hasSavedShares ? 'Custom split saved' : 'Choose custom percentages below' ?></div></div>
    </div>
    <table class="section-title">
        <tr><th>Person</th><th>Split %</th><th>Total Paid</th><th>Cost Share</th><th>Cost Balance</th><th>Profit Share</th><th><?= $car['actual_sale_price'] > 0 ? 'Payout From Sale' : 'Expected Payout' ?></th></tr>
        <?php foreach ($settlements as $name => $settlement): ?>
        <tr>
            <td><?= detail_text($name) ?></td>
            <td><?= $sharePercents[$name] === null ? 'Not set' : number_format($sharePercents[$name], 2).'%' ?></td>
            <td>$<?= number_format($paidTotals[$name], 2) ?></td>
            <td><?= $costShares[$name] === null ? 'Set split' : '$'.number_format($costShares[$name], 2) ?></td>
            <td class="<?= $settlement === null ? '' : ($settlement >= 0 ? 'positive' : 'negative') ?>"><?= $settlement === null ? 'Set split' : ($settlement >= 0 ? 'Ahead $'.number_format($settlement, 2) : 'Behind $'.number_format(abs($settlement), 2)) ?></td>
            <td><?= $profitShares[$name] === null ? 'Set split' : '$'.number_format($profitShares[$name], 2) ?></td>
            <td><b><?= $salePayouts[$name] === null ? 'Set split' : '$'.number_format($salePayouts[$name], 2) ?></b></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$partnerNames): ?>
        <tr><td colspan="7">No car investors recorded yet. Add purchase payments or expenses with Paid By to calculate this car's split.</td></tr>
        <?php endif; ?>
        <?php if ($unassignedExpenses > 0): ?>
        <tr><td>Unassigned expenses</td><td colspan="6">$<?= number_format($unassignedExpenses, 2) ?> needs Paid By set</td></tr>
        <?php endif; ?>
        <?php if ($unassignedPurchase > 0): ?>
        <tr><td>Unassigned purchase amount</td><td colspan="6">$<?= number_format($unassignedPurchase, 2) ?> needs purchase payment records</td></tr>
        <?php endif; ?>
    </table>
    <?php if ($partnerNames): ?>
    <details class="dropdown-card section-title" <?= $hasSavedShares ? '' : 'open' ?>>
        <summary><?= $hasSavedShares ? 'Edit Custom Split' : 'Set Custom Split' ?></summary>
        <form action="../actions/save-profit-shares.php" method="POST">
            <input type="hidden" name="car_id" value="<?= (int) $id ?>">
            <?php foreach ($partnerNames as $name): ?>
            <label><?= detail_text($name) ?> percentage</label>
            <input name="shares[<?= detail_text($name) ?>]" type="number" step="0.01" min="0" max="100" value="<?= $sharePercents[$name] === null ? '' : number_format($sharePercents[$name], 2, '.', '') ?>">
            <?php endforeach; ?>
            <p class="small">Only the people participating in this car appear here. Percentages must add up to 100%.</p>
            <button class="btn" type="submit">Save Split</button>
        </form>
    </details>
    <?php endif; ?>

    <h2 class="section-title">Purchase Payments</h2>
    <table>
        <tr><th>Date</th><th>Paid By</th><th>Amount</th><th>Notes</th><th>Action</th></tr>
        <?php foreach ($purchasePayments as $payment): ?>
        <tr>
            <td><?= detail_text($payment['paid_date'], 'N/A') ?></td>
            <td><?= detail_text($payment['paid_by'], 'N/A') ?></td>
            <td>$<?= number_format($payment['amount'], 2) ?></td>
            <td><?= detail_text($payment['notes']) ?></td>
            <td>
                <div class="row-actions">
                    <a class="btn secondary small-btn" href="edit-purchase-payment.php?id=<?= (int) $payment['id'] ?>">Edit</a>
                    <form action="../actions/delete-purchase-payment.php" method="POST" onsubmit="return confirm('Delete this purchase payment?');">
                        <input type="hidden" name="id" value="<?= (int) $payment['id'] ?>">
                        <button class="btn danger small-btn" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a class="btn" href="add-purchase-payment.php?car_id=<?= $id ?>">+ Add Purchase Payment</a></p>

    <h2 class="section-title">Car Details</h2>
    <div class="card">
        <p><b>Color:</b> <?= detail_text($car['color'], 'N/A') ?></p>
        <p><b>Body Type:</b> <?= detail_text($car['body_type'], 'N/A') ?></p>
        <p><b>VIN:</b> <?= detail_text($car['vin'], 'N/A') ?></p>
        <p><b>Rego:</b> <?= detail_text($car['rego'], 'N/A') ?></p>
        <p><b>Odometer:</b> <?= number_format((int)$car['odometer']) ?> km</p>
        <p><b>Source:</b> <?= detail_text($car['source'], 'N/A') ?></p>
        <p><b>Damage:</b> <?= detail_lines($car['damage_notes']) ?></p>
        <p><b>Notes:</b> <?= detail_lines($car['notes']) ?></p>
    </div>

    <h2 class="section-title">Photos & Documents</h2>
    <div class="grid">
        <?php foreach ($carFiles as $file): ?>
        <div class="card">
            <b><?= detail_text($file['title'], 'Untitled') ?></b>
            <div class="small"><?= detail_text($file['file_type']) ?></div>
            <?php if (preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $file['file_path'])): ?>
            <p><a href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank"><img class="media-thumb" src="../<?= htmlspecialchars($file['file_path']) ?>" alt="<?= detail_text($file['title'], 'Car file') ?>"></a></p>
            <?php else: ?>
            <p><a class="btn secondary small-btn" href="../<?= htmlspecialchars($file['file_path']) ?>" target="_blank">Open Document</a></p>
            <?php endif; ?>
            <p class="small"><?= detail_text($file['notes']) ?></p>
            <form action="../actions/delete-car-file.php" method="POST" onsubmit="return confirm('Delete this file?');">
                <input type="hidden" name="id" value="<?= (int) $file['id'] ?>">
                <button class="btn danger small-btn" type="submit">Delete</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <p><a class="btn" href="add-car-file.php?car_id=<?= $id ?>">+ Add Photo / Document</a></p>

    <h2 class="section-title">Parts</h2>
    <table>
        <tr><th>Part</th><th>Supplier</th><th>Cost</th><th>Status</th><th>Dates</th><th>Action</th></tr>
        <?php foreach ($parts as $part): ?>
        <tr>
            <td><?= detail_text($part['part_name']) ?><div class="small"><?= detail_text($part['notes']) ?></div></td>
            <td><?= detail_text($part['supplier']) ?></td>
            <td>$<?= number_format($part['cost'], 2) ?></td>
            <td><span class="badge"><?= detail_text($part['status']) ?></span></td>
            <td class="small">Ordered <?= detail_text($part['ordered_date'], 'N/A') ?><br>Arrived <?= detail_text($part['arrived_date'], 'N/A') ?><br>Installed <?= detail_text($part['installed_date'], 'N/A') ?></td>
            <td><div class="row-actions"><a class="btn secondary small-btn" href="edit-part.php?id=<?= (int) $part['id'] ?>">Edit</a><form action="../actions/delete-part.php" method="POST" onsubmit="return confirm('Delete this part?');"><input type="hidden" name="id" value="<?= (int) $part['id'] ?>"><button class="btn danger small-btn" type="submit">Delete</button></form></div></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a class="btn" href="add-part.php?car_id=<?= $id ?>">+ Add Part</a></p>

    <h2 class="section-title">Sale Listings & Offers</h2>
    <table>
        <tr><th>Platform</th><th>Price</th><th>Status</th><th>Buyer / Offer</th><th>Notes</th><th>Action</th></tr>
        <?php foreach ($listings as $listing): ?>
        <tr>
            <td><?= detail_text($listing['platform']) ?><div class="small"><?= detail_text($listing['listed_date'], 'N/A') ?></div></td>
            <td>$<?= number_format($listing['listing_price'], 2) ?></td>
            <td><span class="badge"><?= detail_text($listing['status']) ?></span></td>
            <td><?= detail_text($listing['buyer_name']) ?><div class="small"><?= detail_text($listing['buyer_contact']) ?><br>Offer $<?= number_format($listing['offer_amount'], 2) ?> / Deposit $<?= number_format($listing['deposit_amount'], 2) ?></div></td>
            <td><?= detail_text($listing['notes']) ?></td>
            <td><div class="row-actions"><a class="btn secondary small-btn" href="edit-listing.php?id=<?= (int) $listing['id'] ?>">Edit</a><form action="../actions/delete-listing.php" method="POST" onsubmit="return confirm('Delete this listing?');"><input type="hidden" name="id" value="<?= (int) $listing['id'] ?>"><button class="btn danger small-btn" type="submit">Delete</button></form></div></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a class="btn" href="add-listing.php?car_id=<?= $id ?>">+ Add Listing / Offer</a></p>

    <h2 class="section-title">Expenses</h2>
    <table>
        <tr><th>Date</th><th>Category</th><th>Name</th><th>Amount</th><th>Paid By</th><th>Receipt</th><th>Notes</th><th>Action</th></tr>
        <?php foreach ($expenses as $e): ?>
        <tr>
            <td><?= detail_text($e['expense_date'], 'N/A') ?></td>
            <td><?= detail_text($e['category']) ?></td>
            <td><?= detail_text($e['expense_name']) ?></td>
            <td>$<?= number_format($e['amount'], 2) ?></td>
            <td><?= detail_text($e['paid_by']) ?></td>
            <td>
                <?php if (!empty($e['receipt_file'])): ?>
                <a href="../<?= htmlspecialchars($e['receipt_file']) ?>" target="_blank"><img class="thumb" src="../<?= htmlspecialchars($e['receipt_file']) ?>" alt="Receipt"></a>
                <?php endif; ?>
            </td>
            <td><?= detail_text($e['notes']) ?></td>
            <td>
                <div class="row-actions">
                    <a class="btn secondary small-btn" href="edit-expense.php?id=<?= (int) $e['id'] ?>">Edit</a>
                    <form action="../actions/delete-expense.php" method="POST" onsubmit="return confirm('Delete this expense?');">
                        <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                        <button class="btn danger small-btn" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a class="btn" href="add-expense.php?car_id=<?= $id ?>">+ Add Expense</a></p>

    <h2 class="section-title">Tasks</h2>
    <div class="task-board">
        <?php foreach ($tasksByStatus as $statusName => $statusTasks): ?>
        <section class="task-column">
            <div class="task-column-head">
                <b><?= detail_text($statusName) ?></b>
                <span><?= count($statusTasks) ?></span>
            </div>
            <?php foreach ($statusTasks as $boardTask): ?>
            <a class="task-mini-card" href="edit-task.php?id=<?= (int) $boardTask['id'] ?>">
                <b><?= detail_text($boardTask['task_title']) ?></b>
                <span><?= detail_text($boardTask['assigned_to'], 'Unassigned') ?></span>
                <small><?= number_format((float) ($boardTask['hours_spent'] ?? 0), 2) ?> hrs / <?= detail_text($boardTask['priority']) ?></small>
            </a>
            <?php endforeach; ?>
            <?php if (!$statusTasks): ?>
            <p class="small">No tasks here.</p>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
    <table>
        <tr><th>Due</th><th>Task</th><th>Assigned</th><th>Hours</th><th>Priority</th><th>Status</th><th>Action</th></tr>
        <?php foreach ($tasks as $t): ?>
        <?php $assignedNames = array_map('trim', explode(',', $t['assigned_to'] ?? '')); ?>
        <tr>
            <td><?= detail_text($t['due_date'], 'N/A') ?></td>
            <td><?= detail_text($t['task_title']) ?><div class="small"><?= detail_text($t['description']) ?></div></td>
            <td>
                <form action="../actions/update-task-assignee.php" method="POST">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <details class="assign-menu">
                        <summary><?= $assignedNames && implode('', $assignedNames) !== '' ? detail_text(implode(', ', array_filter($assignedNames))) : 'Assign people' ?></summary>
                        <div class="assign-panel">
                            <?php foreach ($users as $name): ?>
                            <label class="check-pill"><input type="checkbox" name="assigned_to[]" value="<?= detail_text($name) ?>" <?= in_array($name, $assignedNames, true) ? 'checked' : '' ?>> <?= detail_text($name) ?></label>
                            <?php endforeach; ?>
                            <button class="btn secondary small-btn" type="submit">Save</button>
                        </div>
                    </details>
                </form>
            </td>
            <td><?= number_format((float) ($t['hours_spent'] ?? 0), 2) ?></td>
            <td><?= detail_text($t['priority']) ?></td>
            <td>
                <form action="../actions/update-task-status.php" method="POST">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <select class="inline-select" name="status" onchange="this.form.submit()">
                        <?php foreach(['To Do','In Progress','Done'] as $status): ?>
                        <option <?= $t['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </td>
            <td>
                <?php if (!empty($t['task_photo'])): ?>
                <a href="../<?= htmlspecialchars($t['task_photo']) ?>" target="_blank"><img class="thumb" src="../<?= htmlspecialchars($t['task_photo']) ?>" alt="Task photo"></a>
                <?php endif; ?>
                <div class="row-actions">
                    <a class="btn secondary small-btn" href="edit-task.php?id=<?= (int) $t['id'] ?>">Edit</a>
                    <form action="../actions/delete-task.php" method="POST" onsubmit="return confirm('Delete this task?');">
                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                        <button class="btn danger small-btn" type="submit">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p><a class="btn" href="add-task.php?car_id=<?= $id ?>">+ Add Task</a></p>
</div>
<?php require '../footer.php'; ?>

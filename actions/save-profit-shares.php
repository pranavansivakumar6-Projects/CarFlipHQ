<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_permission('can_manage_finance');

$carId = post_int('car_id', true);
require_car($pdo, $carId);

$shares = $_POST['shares'] ?? [];
if (!is_array($shares)) {
    http_response_code(400);
    die('Profit shares are invalid.');
}

$validNames = $pdo->query('SELECT name FROM users ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$nameQueries = [
    'SELECT paid_by FROM expenses WHERE car_id = ? AND paid_by IS NOT NULL AND paid_by <> ""',
    'SELECT paid_by FROM car_purchase_payments WHERE car_id = ? AND paid_by IS NOT NULL AND paid_by <> ""',
    'SELECT assigned_to FROM tasks WHERE car_id = ? AND assigned_to IS NOT NULL AND assigned_to <> ""',
    'SELECT person_name FROM car_profit_shares WHERE car_id = ?',
];

foreach ($nameQueries as $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$carId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rawName) {
        foreach (explode(',', (string) $rawName) as $name) {
            $name = trim($name);
            if ($name !== '') {
                $validNames[] = $name;
            }
        }
    }
}

$validNames = array_values(array_unique($validNames));
$validNameLookup = array_flip($validNames);
$cleanShares = [];
$total = 0.0;

foreach ($shares as $name => $percent) {
    $name = trim((string) $name);
    $percent = trim((string) $percent);

    if ($name === '' || !isset($validNameLookup[$name])) {
        continue;
    }

    if ($percent === '') {
        $percent = '0';
    }

    if (!is_numeric($percent) || (float) $percent < 0 || (float) $percent > 100) {
        http_response_code(400);
        die('Profit share percentages must be between 0 and 100.');
    }

    $percent = round((float) $percent, 2);
    if ($percent <= 0) {
        continue;
    }

    $cleanShares[$name] = $percent;
    $total += $percent;
}

if (!$cleanShares) {
    http_response_code(400);
    die('Add at least one profit share.');
}

if (abs($total - 100.0) > 0.01) {
    http_response_code(400);
    die('Profit shares must add up to 100%.');
}

$pdo->beginTransaction();
$stmt = $pdo->prepare('DELETE FROM car_profit_shares WHERE car_id = ?');
$stmt->execute([$carId]);

$stmt = $pdo->prepare('INSERT INTO car_profit_shares (car_id, person_name, share_percent) VALUES (?, ?, ?)');
foreach ($cleanShares as $name => $percent) {
    $stmt->execute([$carId, $name, $percent]);
}
$pdo->commit();

redirect_to('pages/car-detail.php?id=' . $carId . '&shares=1');
?>

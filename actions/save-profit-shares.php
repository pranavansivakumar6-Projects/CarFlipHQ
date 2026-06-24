<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

$carId = post_int('car_id', true);
require_car($pdo, $carId);

$shares = $_POST['shares'] ?? [];
if (!is_array($shares)) {
    http_response_code(400);
    die('Profit shares are invalid.');
}

$validNames = $pdo->query('SELECT name FROM users ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$stmt = $pdo->prepare("
    SELECT paid_by AS name FROM expenses WHERE car_id = ? AND paid_by IS NOT NULL AND paid_by <> ''
    UNION
    SELECT paid_by AS name FROM car_purchase_payments WHERE car_id = ? AND paid_by IS NOT NULL AND paid_by <> ''
    UNION
    SELECT person_name AS name FROM car_profit_shares WHERE car_id = ?
");
$stmt->execute([$carId, $carId, $carId]);
$validNames = array_values(array_unique(array_merge($validNames, $stmt->fetchAll(PDO::FETCH_COLUMN))));
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

<?php
require '../config/db.php';
require '../config/auth.php';

require_admin();

$cars = $pdo->query("SELECT id, year, make, model, status, purchase_price, actual_sale_price, sold_date FROM cars ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$seen = [];
$deleteIds = [];

foreach ($cars as $car) {
    $keyParts = [
        $car['year'] ?? '',
        strtolower(trim((string) ($car['make'] ?? ''))),
        strtolower(trim((string) ($car['model'] ?? ''))),
        strtolower(trim((string) ($car['status'] ?? ''))),
        number_format((float) ($car['purchase_price'] ?? 0), 2, '.', ''),
        number_format((float) ($car['actual_sale_price'] ?? 0), 2, '.', ''),
        $car['sold_date'] ?? '',
    ];
    $key = implode('|', $keyParts);

    if (isset($seen[$key])) {
        $deleteIds[] = (int) $car['id'];
    } else {
        $seen[$key] = (int) $car['id'];
    }
}

if ($deleteIds) {
    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM cars WHERE id IN ($placeholders)");
    $stmt->execute($deleteIds);
}

redirect_to('pages/cars.php?deduped=' . count($deleteIds));
?>

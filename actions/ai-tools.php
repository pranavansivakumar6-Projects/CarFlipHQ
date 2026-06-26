<?php
require '../config/db.php';
require_once '../config/auth.php';
require_once '../config/helpers.php';
require_once '../config/ai.php';

require_permission('can_use_ai');

function car_options(PDO $pdo): array
{
    $accessWhere = car_access_filter_sql('cars');
    return $pdo->query("SELECT id, year, make, model, status, purchase_price, estimated_sale_price, actual_sale_price, damage_notes, notes FROM cars WHERE $accessWhere ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function car_context(PDO $pdo, int $carId): string
{
    require_car($pdo, $carId);
    $stmt = $pdo->prepare('SELECT * FROM cars WHERE id = ?');
    $stmt->execute([$carId]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$car) {
        http_response_code(404);
        die('Car not found.');
    }

    $expenseStmt = $pdo->prepare('SELECT category, expense_name, amount, paid_by, notes FROM expenses WHERE car_id = ? ORDER BY expense_date DESC');
    $expenseStmt->execute([$carId]);
    $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

    $taskStmt = $pdo->prepare('SELECT task_title, description, assigned_to, hours_spent, priority, status FROM tasks WHERE car_id = ? ORDER BY due_date ASC');
    $taskStmt->execute([$carId]);
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

    $partStmt = $pdo->prepare('SELECT part_name, supplier, cost, status, notes FROM parts WHERE car_id = ? ORDER BY created_at DESC');
    $partStmt->execute([$carId]);
    $parts = $partStmt->fetchAll(PDO::FETCH_ASSOC);

    return json_encode([
        'car' => $car,
        'expenses' => $expenses,
        'tasks' => $tasks,
        'parts' => $parts,
    ], JSON_PRETTY_PRINT);
}

function business_context(PDO $pdo): string
{
    $carsAccessWhere = car_access_filter_sql('cars');
    $carJoinAccessWhere = car_access_filter_sql('c');
    $cars = $pdo->query("SELECT id, year, make, model, status, purchase_price, estimated_sale_price, actual_sale_price FROM cars WHERE $carsAccessWhere ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $expenses = $pdo->query("SELECT c.id AS car_id, c.make, c.model, e.category, e.expense_name, e.amount, e.paid_by FROM expenses e JOIN cars c ON c.id = e.car_id WHERE $carJoinAccessWhere ORDER BY e.created_at DESC LIMIT 120")->fetchAll(PDO::FETCH_ASSOC);
    $tasks = $pdo->query("SELECT c.id AS car_id, c.make, c.model, t.task_title, t.assigned_to, t.hours_spent, t.priority, t.status FROM tasks t JOIN cars c ON c.id = t.car_id WHERE $carJoinAccessWhere ORDER BY t.created_at DESC LIMIT 120")->fetchAll(PDO::FETCH_ASSOC);

    return json_encode([
        'cars' => $cars,
        'recent_expenses' => $expenses,
        'recent_tasks' => $tasks,
    ], JSON_PRETTY_PRINT);
}

$tool = $_POST['tool'] ?? '';
$system = 'You are CarFlip HQ assistant. Use only the provided app data. Be practical, concise, and flag anything that needs user confirmation.';
$prompt = '';
$image = null;

if ($tool === 'business_chat') {
    $question = post_string('question', true);
    $prompt = "App data:\n" . business_context($pdo) . "\n\nQuestion:\n" . $question;
} elseif ($tool === 'listing') {
    $carId = post_int('car_id', true);
    $prompt = "Write a marketplace car listing from this car data. Include title, description, key positives, known damage, and honest buyer notes.\n\n" . car_context($pdo, $carId);
} elseif ($tool === 'tasks') {
    $carId = post_int('car_id', true);
    $prompt = "Suggest repair, parts, RWC, detailing, listing, and admin tasks for this car. Return a short checklist with priority and estimated hours.\n\n" . car_context($pdo, $carId);
} elseif ($tool === 'receipt') {
    $notes = post_string('notes', false);
    $image = $_FILES['receipt_image'] ?? null;
    $prompt = "Read this receipt or expense note and extract supplier, date, total amount, likely category, item description, and paid-by hints. If a field is missing, say Unknown.\n\nNotes:\n" . $notes;
} else {
    http_response_code(400);
    die('AI tool is invalid.');
}

$result = ai_text_request($system, $prompt, $image);
$_SESSION['ai_result'] = [
    'ok' => $result['ok'],
    'message' => $result['message'],
];

redirect_to('pages/ai.php?tool=' . urlencode($tool));
?>

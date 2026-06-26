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

function job_document_context(PDO $pdo, string $documentType): array
{
    $carId = post_int('car_id', true);
    $customerName = post_string('customer_name', true);
    $customerContact = post_string('customer_contact');
    $jobTitle = post_string('job_title', true);
    $jobDetails = post_string('job_details', true);
    $labourAmount = post_money('labour_amount');
    $partsAmount = post_money('parts_amount');
    $otherAmount = post_money('other_amount');
    $documentDate = post_date_or_null('document_date') ?: date('Y-m-d');
    $validUntil = post_date_or_null('valid_until');
    $paymentTerms = post_string('payment_terms');
    $notes = post_string('notes');
    $total = $labourAmount + $partsAmount + $otherAmount;

    $documentData = [
        'document_type' => $documentType,
        'business_name' => 'CarFlip HQ',
        'document_date' => $documentDate,
        'customer' => [
            'name' => $customerName,
            'contact' => $customerContact,
        ],
        'job' => [
            'title' => $jobTitle,
            'details' => $jobDetails,
        ],
        'line_items' => [
            ['label' => 'Labour', 'amount' => $labourAmount],
            ['label' => 'Parts', 'amount' => $partsAmount],
            ['label' => 'Other', 'amount' => $otherAmount],
        ],
        'total' => $total,
        'valid_until' => $validUntil,
        'payment_terms' => $paymentTerms,
        'notes' => $notes,
    ];

    return [
        'car_id' => $carId,
        'data' => $documentData,
        'prompt_context' => "Document request:\n" . json_encode($documentData, JSON_PRETTY_PRINT) . "\n\nCar context:\n" . car_context($pdo, $carId),
    ];
}

$tool = $_POST['tool'] ?? '';
$system = 'You are CarFlip HQ assistant. Use only the provided app data. Be practical, concise, and flag anything that needs user confirmation.';
$prompt = '';
$image = null;
$redirectCarId = null;

if ($tool === 'business_chat') {
    $question = post_string('question', true);
    $prompt = "App data:\n" . business_context($pdo) . "\n\nQuestion:\n" . $question;
} elseif ($tool === 'listing') {
    $carId = post_int('car_id', true);
    $redirectCarId = $carId;
    $prompt = "Write a marketplace car listing from this car data. Include title, description, key positives, known damage, and honest buyer notes.\n\n" . car_context($pdo, $carId);
} elseif ($tool === 'tasks') {
    $carId = post_int('car_id', true);
    $redirectCarId = $carId;
    $prompt = "Suggest repair, parts, RWC, detailing, listing, and admin tasks for this car. Return a short checklist with priority and estimated hours.\n\n" . car_context($pdo, $carId);
} elseif ($tool === 'receipt') {
    $notes = post_string('notes', false);
    $image = $_FILES['receipt_image'] ?? null;
    $prompt = "Read this receipt or expense note and extract supplier, date, total amount, likely category, item description, and paid-by hints. If a field is missing, say Unknown.\n\nNotes:\n" . $notes;
} elseif ($tool === 'quotation') {
    $context = job_document_context($pdo, 'Quotation');
    $redirectCarId = $context['car_id'];
    $system = 'You write professional automotive repair quotations for CarFlip HQ. Use only the supplied details. Do not invent tax registration, GST, ABN, payment account numbers, or legal terms unless they are explicitly supplied. Keep it polished and ready to copy into an email or PDF.';
    $prompt = "Create a professional quotation for this vehicle job. Include: quotation title, customer details, vehicle details, job scope, itemised labour/parts/other amounts, total, validity date if supplied, notes/terms, and an acceptance line. Use Australian dollar formatting. If an amount is zero, include it only when useful.\n\n" . $context['prompt_context'];
} elseif ($tool === 'invoice') {
    $context = job_document_context($pdo, 'Invoice');
    $redirectCarId = $context['car_id'];
    $system = 'You write professional automotive repair invoices for CarFlip HQ. Use only the supplied details. Do not invent tax registration, GST, ABN, payment account numbers, or legal terms unless they are explicitly supplied. Keep it polished and ready to copy into an email or PDF.';
    $prompt = "Create a professional invoice for this vehicle job. Include: invoice title, invoice date, customer details, vehicle details, completed work or job scope, itemised labour/parts/other amounts, total due, payment terms if supplied, notes, and a short thank-you line. Use Australian dollar formatting. If an amount is zero, include it only when useful.\n\n" . $context['prompt_context'];
} else {
    http_response_code(400);
    die('AI tool is invalid.');
}

$result = ai_text_request($system, $prompt, $image);
$_SESSION['ai_result'] = [
    'ok' => $result['ok'],
    'message' => $result['message'],
];

$query = 'tool=' . urlencode($tool);
if ($redirectCarId) {
    $query .= '&car_id=' . (int) $redirectCarId;
}

redirect_to('pages/ai.php?' . $query);
?>

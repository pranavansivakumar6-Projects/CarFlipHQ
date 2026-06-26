<?php
function post_string(string $key, bool $required = false): string
{
    $value = trim($_POST[$key] ?? '');
    if ($required && $value === '') {
        http_response_code(400);
        die(ucfirst(str_replace('_', ' ', $key)) . ' is required.');
    }

    return $value;
}

function post_int(string $key, bool $required = false): ?int
{
    $value = trim($_POST[$key] ?? '');
    if ($value === '') {
        if ($required) {
            http_response_code(400);
            die(ucfirst(str_replace('_', ' ', $key)) . ' is required.');
        }

        return null;
    }

    if (!filter_var($value, FILTER_VALIDATE_INT)) {
        http_response_code(400);
        die(ucfirst(str_replace('_', ' ', $key)) . ' must be a whole number.');
    }

    return (int) $value;
}

function post_money(string $key, bool $required = false): float
{
    $value = trim($_POST[$key] ?? '');
    if ($value === '') {
        if ($required) {
            http_response_code(400);
            die(ucfirst(str_replace('_', ' ', $key)) . ' is required.');
        }

        return 0.0;
    }

    if (!is_numeric($value) || (float) $value < 0) {
        http_response_code(400);
        die(ucfirst(str_replace('_', ' ', $key)) . ' must be a positive number.');
    }

    return (float) $value;
}

function post_date_or_null(string $key): ?string
{
    $value = trim($_POST[$key] ?? '');
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        http_response_code(400);
        die(ucfirst(str_replace('_', ' ', $key)) . ' must be a valid date.');
    }

    return $value;
}

function require_allowed_value(string $value, array $allowed, string $field): string
{
    if (!in_array($value, $allowed, true)) {
        http_response_code(400);
        die(ucfirst(str_replace('_', ' ', $field)) . ' is invalid.');
    }

    return $value;
}

function require_car(PDO $pdo, int $carId): void
{
    $stmt = $pdo->prepare('SELECT id FROM cars WHERE id = ?');
    $stmt->execute([$carId]);

    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        die('Car not found.');
    }

    if (function_exists('user_can_access_car') && !user_can_access_car($pdo, $carId)) {
        http_response_code(403);
        die('You do not have access to this car.');
    }
}

function car_access_filter_sql(string $carAlias = 'cars'): string
{
    if (!function_exists('current_user')) {
        return '1=0';
    }

    $user = current_user();
    if (!$user) {
        return '1=0';
    }

    if (($user['role'] ?? '') === 'admin') {
        return '1=1';
    }

    $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $carAlias);
    if ($safeAlias === '') {
        $safeAlias = 'cars';
    }

    return 'EXISTS (SELECT 1 FROM car_user_access cua WHERE cua.car_id = ' . $safeAlias . '.id AND cua.user_id = ' . (int) $user['id'] . ')';
}

function user_can_access_car(PDO $pdo, int $carId): bool
{
    if (!function_exists('current_user')) {
        return false;
    }

    $user = current_user();
    if (!$user) {
        return false;
    }

    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM car_user_access WHERE car_id = ? AND user_id = ?');
    $stmt->execute([$carId, (int) $user['id']]);

    return (int) $stmt->fetchColumn() > 0;
}

function post_user_ids(string $key): array
{
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        $values = [$values];
    }

    return array_values(array_unique(array_filter(array_map('intval', $values), fn ($id) => $id > 0)));
}

function sync_car_user_access(PDO $pdo, int $carId, array $userIds): void
{
    $pdo->prepare('DELETE FROM car_user_access WHERE car_id = ?')->execute([$carId]);

    if (!$userIds) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
    $stmt->execute($userIds);
    $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $insert = $pdo->prepare('INSERT IGNORE INTO car_user_access (car_id, user_id) VALUES (?, ?)');
    foreach ($validIds as $userId) {
        $insert->execute([$carId, $userId]);
    }
}

function post_user_names(PDO $pdo, string $key): string
{
    $values = $_POST[$key] ?? [];
    if (!is_array($values)) {
        $values = [$values];
    }

    $names = array_values(array_unique(array_filter(array_map('trim', $values))));
    if (!$names) {
        return '';
    }

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $stmt = $pdo->prepare("SELECT name FROM users WHERE name IN ($placeholders)");
    $stmt->execute($names);
    $validNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($validNames) !== count($names)) {
        http_response_code(400);
        die('Assigned user is invalid.');
    }

    return implode(', ', $names);
}

function save_uploaded_image(string $field, string $folder): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        die('Image upload failed.');
    }

    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        http_response_code(400);
        die('Image must be 5MB or smaller.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        die('Only JPG, PNG, WebP, or GIF images are allowed.');
    }

    $safeFolder = trim($folder, '/');
    $uploadDir = dirname(__DIR__) . '/uploads/' . $safeFolder;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        die('Could not create upload folder.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        http_response_code(500);
        die('Could not save uploaded image.');
    }

    return 'uploads/' . $safeFolder . '/' . $filename;
}

function save_uploaded_file(string $field, string $folder): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        die('File upload failed.');
    }

    if ($_FILES[$field]['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        die('File must be 10MB or smaller.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        http_response_code(400);
        die('Only image or PDF files are allowed.');
    }

    $safeFolder = trim($folder, '/');
    $uploadDir = dirname(__DIR__) . '/uploads/' . $safeFolder;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        die('Could not create upload folder.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        http_response_code(500);
        die('Could not save uploaded file.');
    }

    return 'uploads/' . $safeFolder . '/' . $filename;
}

function delete_uploaded_file(?string $path): void
{
    if (!$path) {
        return;
    }

    $fullPath = dirname(__DIR__) . '/' . ltrim($path, '/');
    $uploadsRoot = realpath(dirname(__DIR__) . '/uploads');
    $filePath = realpath($fullPath);

    $uploadsRoot = $uploadsRoot ? rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : false;
    if ($uploadsRoot && $filePath && strpos($filePath, $uploadsRoot) === 0 && is_file($filePath)) {
        unlink($filePath);
    }
}
?>

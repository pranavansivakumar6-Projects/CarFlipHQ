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
}
?>

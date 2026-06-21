<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const BASE_PATH = '/carfliphq';

function app_url(string $path): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function redirect_to(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect_to('pages/login.php');
    }
}

function redirect_if_logged_in(): void
{
    if (is_logged_in()) {
        redirect_to('index.php');
    }
}

function user_count(PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}
?>

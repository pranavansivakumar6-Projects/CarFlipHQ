<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_PATH')) {
    $configuredBasePath = getenv('APP_BASE_PATH');
    $defaultBasePath = (getenv('RAILWAY_PROJECT_ID') || getenv('RAILWAY_ENVIRONMENT')) ? '' : '/carfliphq';
    define('BASE_PATH', rtrim($configuredBasePath !== false ? $configuredBasePath : $defaultBasePath, '/'));
}

function app_url(string $path): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function app_absolute_url(string $path): string
{
    $configuredUrl = trim((string) (getenv('APP_URL') ?: ''));
    if ($configuredUrl !== '') {
        return rtrim($configuredUrl, '/') . app_url($path);
    }

    $host = $_SERVER['HTTP_HOST'] ?? (getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'localhost');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('RAILWAY_ENVIRONMENT');
    $scheme = $https ? 'https' : 'http';

    return $scheme . '://' . $host . app_url($path);
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

    $pdo = $GLOBALS['pdo'] ?? null;
    $user = current_user();
    if ($pdo instanceof PDO && $user) {
        $stmt = $pdo->prepare('SELECT name, email, role, session_version FROM users WHERE id = ?');
        $stmt->execute([(int) $user['id']]);
        $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$freshUser) {
            unset($_SESSION['user']);
            redirect_to('pages/login.php');
        }

        $sessionVersion = $_SESSION['user']['session_version'] ?? null;
        $freshVersion = (int) ($freshUser['session_version'] ?? 0);
        if ($sessionVersion !== null && (int) $sessionVersion !== $freshVersion) {
            unset($_SESSION['user']);
            redirect_to('pages/login.php?changed=1');
        }

        $_SESSION['user']['name'] = $freshUser['name'];
        $_SESSION['user']['email'] = $freshUser['email'];
        $_SESSION['user']['role'] = $freshUser['role'];
        $_SESSION['user']['session_version'] = $freshVersion;
    }
}

function require_admin(): void
{
    require_login();

    if ((current_user()['role'] ?? '') !== 'admin') {
        http_response_code(403);
        die('Admin access required.');
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

function send_app_email(string $to, string $subject, string $message): bool
{
    $from = trim((string) (getenv('APP_FROM_EMAIL') ?: ''));
    if ($from === '') {
        return false;
    }

    $headers = [
        'From: CarFlip HQ <' . $from . '>',
        'Reply-To: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($to, $subject, $message, implode("\r\n", $headers));
}
?>

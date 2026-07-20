<?php
require '../config/auth.php';
require_permission('can_view_imports');

header('Content-Type: application/json');

$url = 'https://api.frankfurter.dev/v2/rate/AUD/JPY';
$body = false;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
        $body = false;
    }
} else {
    $context = stream_context_create([
        'http' => [
            'header' => "Accept: application/json\r\n",
            'timeout' => 8,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
}

$data = $body ? json_decode($body, true) : null;
$rate = (float) ($data['rate'] ?? 0);

if ($rate <= 0) {
    http_response_code(502);
    echo json_encode(['error' => 'Live AUD/JPY rate is unavailable.']);
    exit;
}

echo json_encode([
    'base' => 'AUD',
    'quote' => 'JPY',
    'rate' => $rate,
    'date' => $data['date'] ?? null,
]);
exit;
?>

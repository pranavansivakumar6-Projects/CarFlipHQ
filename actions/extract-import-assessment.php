<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require '../config/ai.php';

require_permission('can_view_imports');
require_permission('can_use_ai');

header('Content-Type: application/json');

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function extract_json_object(string $text): ?array
{
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
    return is_array($decoded) ? $decoded : null;
}

function host_is_public(string $host): bool
{
    $ip = gethostbyname($host);
    if (!$ip || $ip === $host) {
        return false;
    }

    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function fetch_public_url_text(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '' || !host_is_public($host)) {
        json_response(['ok' => false, 'message' => 'Use a public http or https auction/listing link.'], 400);
    }

    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'CarFlipHQ/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($status < 200 || $status >= 300 || stripos($contentType, 'text/html') === false) {
            $body = false;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: CarFlipHQ/1.0\r\n",
                'timeout' => 15,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
    }

    if (!$body) {
        json_response(['ok' => false, 'message' => 'Could not read that link. Try uploading a screenshot or saved auction sheet image.'], 400);
    }

    $text = html_entity_decode(strip_tags((string) $body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return substr($text, 0, 12000);
}

if (!ai_is_available()) {
    json_response(['ok' => false, 'message' => 'AI is not connected yet. Add OPENAI_API_KEY in Railway Variables, then redeploy.'], 400);
}

$sourceUrl = post_string('source_url');
$image = $_FILES['auction_sheet_image'] ?? null;
if ($sourceUrl === '' && (!$image || ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
    json_response(['ok' => false, 'message' => 'Upload an auction sheet image or paste an auction/listing URL.'], 400);
}
if ($image && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && ($image['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    json_response(['ok' => false, 'message' => 'Image upload failed. Try a smaller JPEG or PNG auction sheet.'], 400);
}

$urlText = fetch_public_url_text($sourceUrl);
$system = 'You extract Japanese vehicle auction sheet and listing data for CarFlip HQ. Return only valid JSON. Do not guess unavailable values.';
$prompt = "Extract fields from the supplied auction sheet image or listing text. Return ONLY this JSON object with string values unless noted:\n"
    . "{\n"
    . "  \"make\": \"\",\n"
    . "  \"model\": \"\",\n"
    . "  \"variant\": \"\",\n"
    . "  \"year\": \"\",\n"
    . "  \"chassis_vin\": \"\",\n"
    . "  \"mileage\": \"\",\n"
    . "  \"auction_house\": \"\",\n"
    . "  \"auction_date\": \"YYYY-MM-DD or empty\",\n"
    . "  \"auction_grade\": \"\",\n"
    . "  \"interior_grade\": \"\",\n"
    . "  \"lot_number\": \"\",\n"
    . "  \"japan_agent\": \"\",\n"
    . "  \"damage_notes\": \"\",\n"
    . "  \"notes\": \"\",\n"
    . "  \"confidence\": \"high, medium, or low\"\n"
    . "}\n\n"
    . "Rules: translate make, model, variant, notes, and damage_notes to clear English when the source is Japanese. Keep chassis/VIN, auction house, grades, and lot numbers exactly as written. Convert mileage to numbers only when clear, convert dates to YYYY-MM-DD when clear, put uncertain notes in notes, and leave unknown fields empty.\n\n"
    . ($sourceUrl ? "Source URL: $sourceUrl\nFetched page text:\n$urlText" : 'Use the uploaded image.');

$result = ai_text_request($system, $prompt, $image);
if (!$result['ok']) {
    json_response(['ok' => false, 'message' => $result['message']], 400);
}

$fields = extract_json_object($result['message']);
if (!$fields) {
    json_response(['ok' => false, 'message' => 'AI returned a response, but it was not structured enough to apply. Try a clearer image.'], 400);
}

$allowed = ['make','model','variant','year','chassis_vin','mileage','auction_house','auction_date','auction_grade','interior_grade','lot_number','japan_agent','damage_notes','notes','confidence'];
$clean = [];
foreach ($allowed as $key) {
    $clean[$key] = trim((string) ($fields[$key] ?? ''));
}

json_response(['ok' => true, 'fields' => $clean]);
?>

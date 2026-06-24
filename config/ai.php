<?php
function ai_api_key(): string
{
    return trim((string) getenv('OPENAI_API_KEY'));
}

function ai_model(): string
{
    return trim((string) (getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini'));
}

function ai_is_available(): bool
{
    return ai_api_key() !== '';
}

function ai_text_request(string $systemPrompt, string $userPrompt, ?array $imageUpload = null): array
{
    if (!ai_is_available()) {
        return [
            'ok' => false,
            'message' => 'AI is not connected yet. Add OPENAI_API_KEY in Railway Variables, then redeploy.',
        ];
    }

    $content = [
        ['type' => 'input_text', 'text' => $userPrompt],
    ];

    if ($imageUpload && ($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $mime = mime_content_type($imageUpload['tmp_name']);
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($imageUpload['tmp_name'])),
            ];
        }
    }

    $payload = [
        'model' => ai_model(),
        'input' => [
            [
                'role' => 'system',
                'content' => [
                    ['type' => 'input_text', 'text' => $systemPrompt],
                ],
            ],
            [
                'role' => 'user',
                'content' => $content,
            ],
        ],
    ];

    $encodedPayload = json_encode($payload);
    $headers = [
        'Authorization: Bearer ' . ai_api_key(),
        'Content-Type: application/json',
    ];
    $body = false;
    $status = 0;
    $error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $encodedPayload,
            CURLOPT_TIMEOUT => 45,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $encodedPayload,
                'timeout' => 45,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents('https://api.openai.com/v1/responses', false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            $status = (int) $matches[1];
        }
        $error = $body === false ? 'Could not reach the OpenAI API.' : '';
    }

    if ($body === false || $error !== '') {
        return ['ok' => false, 'message' => 'AI request failed: ' . $error];
    }

    $decoded = json_decode($body, true);
    if ($status >= 400) {
        $message = $decoded['error']['message'] ?? ('OpenAI returned HTTP ' . $status);
        return ['ok' => false, 'message' => $message];
    }

    $text = $decoded['output_text'] ?? '';
    if ($text === '' && isset($decoded['output']) && is_array($decoded['output'])) {
        foreach ($decoded['output'] as $item) {
            foreach (($item['content'] ?? []) as $part) {
                if (($part['type'] ?? '') === 'output_text' && isset($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }
    }

    return [
        'ok' => true,
        'message' => trim($text) ?: 'AI finished, but did not return text.',
    ];
}
?>

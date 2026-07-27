<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_permission('can_view_imports');

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId) {
    http_response_code(404);
    die('Document not found.');
}

$stmt = $pdo->prepare('
    SELECT *
    FROM import_documents
    WHERE id = ? AND archived_at IS NULL
');
$stmt->execute([(int) $documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$document) {
    http_response_code(404);
    die('Document not found.');
}

require_import_assessment($pdo, (int) $document['assessment_id']);

$path = dirname(__DIR__) . '/' . ltrim((string) $document['stored_path'], '/');
$uploadsRoot = realpath(dirname(__DIR__) . '/uploads');
$filePath = realpath($path);
if (!$uploadsRoot || !$filePath || strpos($filePath, rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    die('Document file not found.');
}

$filename = basename((string) $document['original_filename']);
header('Content-Type: ' . ((string) $document['mime_type'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
readfile($filePath);
exit;
?>

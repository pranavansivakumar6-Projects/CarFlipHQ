<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require '../config/import-costs.php';

require_permission('can_manage_imports');

$user = current_user();
$assessmentId = post_int('assessment_id', true);
require_import_assessment($pdo, (int) $assessmentId);

try {
    $result = import_save_report_upload($pdo, $_FILES['japan_report'] ?? [], (int) $assessmentId, (int) $user['id']);

    $details = 'Uploaded ' . $result['report_type'] . ' report for review.';
    if (!empty($result['duplicate_document_id'])) {
        $details .= ' Duplicate checksum warning: this file already exists as document #' . (int) $result['duplicate_document_id'] . '.';
    }
    $audit = $pdo->prepare('INSERT INTO import_audit_log (assessment_id, user_id, action, details) VALUES (?, ?, ?, ?)');
    $audit->execute([(int) $assessmentId, (int) $user['id'], 'report_uploaded', $details]);

    redirect_to('pages/review-import-report.php?id=' . (int) $result['report_id']);
} catch (Throwable $e) {
    error_log('Import report upload failed: ' . $e->getMessage());
    redirect_to('pages/import-calculator.php?id=' . (int) $assessmentId . '&save_error=' . urlencode($e->getMessage()));
}
?>

<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require '../config/import-costs.php';

require_permission('can_manage_imports');
require_permission('can_view_import_finance');

$user = current_user();
$reportId = post_int('report_id', true);

$stmt = $pdo->prepare('
    SELECT icr.*, idoc.id AS document_id
    FROM import_cost_reports icr
    JOIN import_documents idoc ON idoc.id = icr.document_id
    WHERE icr.id = ?
');
$stmt->execute([(int) $reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) {
    http_response_code(404);
    die('Report not found.');
}

$assessmentId = (int) $report['assessment_id'];
require_import_assessment($pdo, $assessmentId);

$postedItems = $_POST['items'] ?? [];
if (!is_array($postedItems) || !$postedItems) {
    redirect_to('pages/review-import-report.php?id=' . (int) $reportId . '&error=' . urlencode('There are no cost lines to approve.'));
}

$items = [];
foreach ($postedItems as $item) {
    if (!is_array($item)) {
        continue;
    }
    $code = require_allowed_value((string) ($item['cost_code'] ?? ''), array_keys(import_cost_definitions()), 'cost_code');
    $definition = import_cost_definition($code);
    $status = require_allowed_value((string) ($item['status'] ?? 'Estimated'), import_cost_statuses(), 'status');
    $treatment = require_allowed_value((string) ($item['treatment'] ?? 'Separate'), import_cost_treatments(), 'treatment');
    $actualRaw = trim((string) ($item['actual_amount'] ?? ''));

    $costItem = [
        'cost_code' => $code,
        'category' => $definition[1],
        'description' => $definition[0],
        'stage' => $definition[2],
        'low_estimate' => max(0, (float) ($item['low_estimate'] ?? 0)),
        'high_estimate' => max(0, (float) ($item['high_estimate'] ?? 0)),
        'actual_amount' => $actualRaw === '' ? null : max(0, (float) $actualRaw),
        'currency' => 'AUD',
        'status' => $status,
        'treatment' => $treatment,
        'conditional_flag' => in_array($code, ['SHIP_EBS', 'SHIP_BAF', 'SHIP_HEAT_TREATMENT', 'JP_STORAGE'], true) ? 1 : 0,
        'source_label' => substr(trim((string) ($item['source_label'] ?? '')), 0, 180),
        'source_cell' => substr(trim((string) ($item['source_cell'] ?? '')), 0, 40),
        'notes' => trim((string) ($item['notes'] ?? '')),
    ];
    [$cleanLow, $cleanHigh] = import_cost_item_estimates($costItem);
    $costItem['low_estimate'] = $cleanLow;
    $costItem['high_estimate'] = $cleanHigh;
    $items[] = $costItem;
}

$payload = json_decode((string) ($report['parsed_payload'] ?? ''), true) ?: [];
$reported = $payload['reported_total'] ?? ['low' => 0, 'high' => 0];
$summary = import_calculate_cost_summary($items);
$hasFobTotal = array_filter($items, fn ($item) => (string) ($item['cost_code'] ?? '') === 'JP_APPROVED_FOB');
$scope = (string) $report['report_type'] === 'CIF Budget' ? ($hasFobTotal ? 'CIF' : 'Shipping/CIF') : 'FOB';
$calculatedLow = $scope === 'CIF' ? $summary['cif_low'] : ($scope === 'Shipping/CIF' ? $summary['shipping_low'] : $summary['fob_low']);
$calculatedHigh = $scope === 'CIF' ? $summary['cif_high'] : ($scope === 'Shipping/CIF' ? $summary['shipping_high'] : $summary['fob_high']);
$varianceLow = (float) ($reported['low'] ?? 0) - $calculatedLow;
$varianceHigh = (float) ($reported['high'] ?? 0) - $calculatedHigh;
$severity = (abs($varianceLow) > 0.01 || abs($varianceHigh) > 0.01) ? 'Warning' : 'Info';

try {
    $pdo->beginTransaction();

    $existingStmt = $pdo->prepare('SELECT * FROM import_cost_items WHERE assessment_id = ? AND cost_code = ? ORDER BY id DESC LIMIT 1');
    $insert = $pdo->prepare('
        INSERT INTO import_cost_items
            (assessment_id, report_id, document_id, cost_code, category, description, stage, low_estimate, high_estimate, actual_amount, currency, status, treatment, conditional_flag, source_label, source_cell, notes, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $history = $pdo->prepare('
        INSERT INTO import_cost_history
            (assessment_id, cost_item_id, report_id, user_id, action, before_value, after_value, reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($items as $item) {
        $existingStmt->execute([$assessmentId, $item['cost_code']]);
        $before = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $insert->execute([
            $assessmentId,
            (int) $reportId,
            (int) $report['document_id'],
            $item['cost_code'],
            $item['category'],
            $item['description'],
            $item['stage'],
            $item['low_estimate'],
            $item['high_estimate'],
            $item['actual_amount'],
            $item['currency'],
            $item['status'],
            $item['treatment'],
            $item['conditional_flag'],
            $item['source_label'],
            $item['source_cell'],
            $item['notes'],
            (int) $user['id'],
            (int) $user['id'],
        ]);
        $costItemId = (int) $pdo->lastInsertId();
        $history->execute([
            $assessmentId,
            $costItemId,
            (int) $reportId,
            (int) $user['id'],
            $before ? 'cost_version_added' : 'cost_created',
            $before ? json_encode($before) : null,
            json_encode($item),
            'Approved from ' . (string) $report['report_type'] . ' report.',
        ]);
    }

    $recon = $pdo->prepare('
        INSERT INTO import_cost_reconciliations
            (assessment_id, report_id, cost_scope, reported_low, reported_high, calculated_low, calculated_high, variance_low, variance_high, severity)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $recon->execute([
        $assessmentId,
        (int) $reportId,
        $scope,
        (float) ($reported['low'] ?? 0),
        (float) ($reported['high'] ?? 0),
        $calculatedLow,
        $calculatedHigh,
        $varianceLow,
        $varianceHigh,
        $severity,
    ]);

    $pdo->prepare('UPDATE import_cost_reports SET approval_status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?')
        ->execute(['Approved', (int) $user['id'], (int) $reportId]);

    $audit = $pdo->prepare('INSERT INTO import_audit_log (assessment_id, user_id, action, details) VALUES (?, ?, ?, ?)');
    $audit->execute([
        $assessmentId,
        (int) $user['id'],
        'report_approved',
        (string) $report['report_type'] . ' report approved. ' . count($items) . ' cost lines committed. ' . $scope . ' variance low/high: ' . number_format($varianceLow, 2) . ' / ' . number_format($varianceHigh, 2) . '.',
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Import report approval failed: ' . $e->getMessage());
    redirect_to('pages/review-import-report.php?id=' . (int) $reportId . '&error=' . urlencode('Could not approve the report: ' . $e->getMessage()));
}

redirect_to('pages/import-calculator.php?id=' . $assessmentId . '&report_approved=1');
?>

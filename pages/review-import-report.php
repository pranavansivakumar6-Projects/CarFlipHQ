<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_manage_imports');
require_permission('can_view_import_finance');
require_once '../config/helpers.php';
require_once '../config/import-costs.php';

$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$reportId) {
    http_response_code(404);
    die('Report not found.');
}

$stmt = $pdo->prepare('
    SELECT icr.*, idoc.original_filename, idoc.stored_path, ia.import_ref, ia.make, ia.model, ia.year
    FROM import_cost_reports icr
    JOIN import_documents idoc ON idoc.id = icr.document_id
    JOIN import_assessments ia ON ia.id = icr.assessment_id
    WHERE icr.id = ?
');
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$report) {
    http_response_code(404);
    die('Report not found.');
}

require_import_assessment($pdo, (int) $report['assessment_id']);

$payload = json_decode((string) ($report['parsed_payload'] ?? ''), true) ?: [];
$items = $payload['items'] ?? [];
$reportedTotal = $payload['reported_total'] ?? ['low' => 0, 'high' => 0];
$summary = import_calculate_cost_summary($items);
$calculatedScope = $report['report_type'] === 'CIF Budget' ? 'Shipping/CIF charges' : 'FOB total';
$calculatedLow = $report['report_type'] === 'CIF Budget' ? $summary['shipping_low'] : $summary['fob_low'];
$calculatedHigh = $report['report_type'] === 'CIF Budget' ? $summary['shipping_high'] : $summary['fob_high'];
$varianceLow = (float) ($reportedTotal['low'] ?? 0) - $calculatedLow;
$varianceHigh = (float) ($reportedTotal['high'] ?? 0) - $calculatedHigh;

$pageTitle = 'Review Japan Report | CarFlip HQ';
require '../header.php';
?>
<div class="container import-calculator-view">
    <div class="page-heading">
        <div>
            <div class="eyebrow">Japan Report Review</div>
            <h1><?= htmlspecialchars((string) $report['report_type']) ?></h1>
            <p class="small"><?= htmlspecialchars(trim((string) $report['year'] . ' ' . (string) $report['make'] . ' ' . (string) $report['model'])) ?> · <?= htmlspecialchars((string) $report['import_ref']) ?></p>
        </div>
        <a class="btn secondary" href="import-calculator.php?id=<?= (int) $report['assessment_id'] ?>">Back to Assessment</a>
    </div>

    <?php if ((string) $report['approval_status'] === 'Approved'): ?>
        <div class="alert success">This report has already been approved.</div>
    <?php endif; ?>

    <?php foreach (($payload['warnings'] ?? []) as $warning): ?>
        <div class="alert warning"><?= htmlspecialchars((string) $warning) ?></div>
    <?php endforeach; ?>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert error"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <section class="form-card import-section-card">
        <div class="section-kicker">Source File</div>
        <h2><?= htmlspecialchars((string) $report['original_filename']) ?></h2>
        <div class="report-meta-grid">
            <div><span>Detected type</span><strong><?= htmlspecialchars((string) $report['report_type']) ?></strong></div>
            <div><span>Parser confidence</span><strong><?= number_format((float) $report['parser_confidence'] * 100, 0) ?>%</strong></div>
            <div><span>Status</span><strong><?= htmlspecialchars((string) $report['approval_status']) ?></strong></div>
            <div><span>Sheets</span><strong><?= htmlspecialchars(implode(', ', $payload['sheet_names'] ?? [])) ?></strong></div>
        </div>
    </section>

    <form method="post" action="<?= app_url('actions/approve-import-report.php') ?>">
        <input type="hidden" name="report_id" value="<?= (int) $report['id'] ?>">

        <section class="form-card import-section-card">
            <div class="section-kicker">Review Before Commit</div>
            <h2>Mapped Cost Lines</h2>
            <?php if (!$items): ?>
                <div class="empty-state">No automatic cost lines were found. Save the workbook as .xlsx or add costs manually in the next phase.</div>
            <?php else: ?>
                <div class="table-wrap report-review-wrap">
                    <table class="report-review-table">
                        <thead>
                            <tr>
                                <th>Source Label</th>
                                <th>Target Field</th>
                                <th>Low</th>
                                <th>High</th>
                                <th>Actual</th>
                                <th>Status</th>
                                <th>Treatment</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="items[<?= $index ?>][source_label]" value="<?= htmlspecialchars((string) ($item['source_label'] ?? '')) ?>">
                                    <input type="hidden" name="items[<?= $index ?>][source_cell]" value="<?= htmlspecialchars((string) ($item['source_cell'] ?? '')) ?>">
                                    <small><?= htmlspecialchars((string) ($item['source_label'] ?? '')) ?></small>
                                </td>
                                <td>
                                    <select name="items[<?= $index ?>][cost_code]">
                                        <?php foreach (import_cost_definitions() as $code => $definition): ?>
                                            <option value="<?= htmlspecialchars($code) ?>" <?= ($item['cost_code'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($definition[0]) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input class="money-input" type="number" step="0.01" name="items[<?= $index ?>][low_estimate]" value="<?= htmlspecialchars((string) ($item['low_estimate'] ?? 0)) ?>"></td>
                                <td><input class="money-input" type="number" step="0.01" name="items[<?= $index ?>][high_estimate]" value="<?= htmlspecialchars((string) ($item['high_estimate'] ?? 0)) ?>"></td>
                                <td><input class="money-input" type="number" step="0.01" name="items[<?= $index ?>][actual_amount]" value="<?= htmlspecialchars((string) ($item['actual_amount'] ?? '')) ?>"></td>
                                <td>
                                    <select name="items[<?= $index ?>][status]">
                                        <?php foreach (import_cost_statuses() as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>" <?= ($item['status'] ?? 'Estimated') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="items[<?= $index ?>][treatment]">
                                        <?php foreach (import_cost_treatments() as $treatment): ?>
                                            <option value="<?= htmlspecialchars($treatment) ?>" <?= ($item['treatment'] ?? 'Separate') === $treatment ? 'selected' : '' ?>><?= htmlspecialchars($treatment) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input name="items[<?= $index ?>][notes]" value="<?= htmlspecialchars((string) ($item['notes'] ?? '')) ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="form-card import-section-card">
            <div class="section-kicker">Reconciliation</div>
            <h2>Reported Total vs System Total</h2>
            <p class="small">System total checked here: <?= htmlspecialchars($calculatedScope) ?>.</p>
            <div class="report-meta-grid">
                <div><span>Reported low</span><strong>$<?= number_format((float) ($reportedTotal['low'] ?? 0), 2) ?></strong></div>
                <div><span>Reported high</span><strong>$<?= number_format((float) ($reportedTotal['high'] ?? 0), 2) ?></strong></div>
                <div><span>Calculated low</span><strong>$<?= number_format($calculatedLow, 2) ?></strong></div>
                <div><span>Calculated high</span><strong>$<?= number_format($calculatedHigh, 2) ?></strong></div>
            </div>
            <?php if (abs($varianceLow) > 0.01 || abs($varianceHigh) > 0.01): ?>
                <div class="alert warning">Variance detected: low <?= number_format($varianceLow, 2) ?>, high <?= number_format($varianceHigh, 2) ?>. This will be recorded instead of silently corrected.</div>
            <?php endif; ?>
        </section>

        <?php if ((string) $report['approval_status'] !== 'Approved' && $items): ?>
            <div class="actions">
                <button class="btn" type="submit">Approve & Commit Costs</button>
            </div>
        <?php endif; ?>
    </form>
</div>
<?php require '../footer.php'; ?>

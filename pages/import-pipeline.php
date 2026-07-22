<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_view_imports');
require_once '../config/helpers.php';
require_once '../config/import-status.php';

$pageTitle = 'Japan Import Pipeline | CarFlip HQ';
$accessWhere = import_access_filter_sql('ia');
$canManageImports = user_can('can_manage_imports');
$canViewFinance = user_can('can_view_import_finance');
$statusUpdated = isset($_GET['status_updated']);

$assessments = $pdo->query("
    SELECT ia.*, creator.name AS creator_name
    FROM import_assessments ia
    LEFT JOIN users creator ON creator.id = ia.created_by
    WHERE $accessWhere
    ORDER BY ia.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$statuses = import_status_steps();
$byStatus = array_fill_keys($statuses, []);
foreach ($assessments as $assessment) {
    $status = normalise_import_status((string) ($assessment['status'] ?? ''));
    if (!isset($byStatus[$status])) {
        $status = 'Under Assessment';
    }
    $assessment['status'] = $status;
    $byStatus[$status][] = $assessment;
}

function import_pipeline_vehicle_title(array $assessment): string
{
    $title = trim((string) ($assessment['year'] ?? '') . ' ' . (string) ($assessment['make'] ?? '') . ' ' . (string) ($assessment['model'] ?? ''));
    return $title !== '' ? $title : 'Unnamed import';
}

require '../header.php';
?>
<div class="container imports-view">
    <?php if ($statusUpdated): ?>
        <div class="alert success">Import status updated.</div>
    <?php endif; ?>

    <div class="page-heading">
        <div>
            <div class="eyebrow">Japan Import Hub</div>
            <h1>Import Pipeline</h1>
            <p class="small">Move vehicles through assessment, auction, approval, shipping, compliance, and sale stages.</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="imports.php">Japan Hub</a>
            <?php if ($canManageImports): ?>
                <a class="btn" href="import-calculator.php">+ New Assessment</a>
            <?php endif; ?>
        </div>
    </div>

    <section class="pipeline-board" aria-label="Japan import pipeline">
        <?php foreach ($statuses as $status): ?>
            <div class="pipeline-column">
                <div class="pipeline-column-head">
                    <h2><?= htmlspecialchars($status) ?></h2>
                    <span><?= count($byStatus[$status]) ?></span>
                </div>
                <div class="pipeline-column-body">
                    <?php foreach ($byStatus[$status] as $assessment): ?>
                        <?php
                        $snapshot = json_decode((string) ($assessment['calculation_snapshot'] ?? ''), true) ?: [];
                        $profit = (float) ($snapshot['expected_profit_aud'] ?? 0);
                        ?>
                        <article class="pipeline-card">
                            <div class="pipeline-card-title">
                                <strong><?= htmlspecialchars(import_pipeline_vehicle_title($assessment)) ?></strong>
                                <span><?= htmlspecialchars((string) ($assessment['import_ref'] ?? '')) ?></span>
                            </div>
                            <div class="pipeline-meta">
                                <span>Lot <?= htmlspecialchars((string) ($assessment['lot_number'] ?: 'TBC')) ?></span>
                                <span><?= htmlspecialchars((string) ($assessment['auction_house'] ?: 'Auction TBC')) ?></span>
                            </div>
                            <?php if ($canViewFinance): ?>
                                <div class="pipeline-finance">
                                    <span>Total $<?= number_format((float) ($snapshot['total_pre_sale_cost_aud'] ?? 0), 2) ?></span>
                                    <strong class="<?= $profit >= 0 ? 'positive' : 'negative' ?>">Profit $<?= number_format($profit, 2) ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="row-actions">
                                <a class="btn secondary small-btn" href="import-calculator.php?id=<?= (int) $assessment['id'] ?>">Open</a>
                                <?php if ($canManageImports): ?>
                                    <form class="status-form" action="../actions/update-import-status.php" method="POST">
                                        <input type="hidden" name="id" value="<?= (int) $assessment['id'] ?>">
                                        <input type="hidden" name="return_to" value="pipeline">
                                        <select name="status" onchange="this.form.submit()">
                                            <?php foreach ($statuses as $option): ?>
                                                <option value="<?= htmlspecialchars($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$byStatus[$status]): ?>
                        <div class="pipeline-empty">No vehicles</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="form-card section-title">
        <h2>Pipeline List</h2>
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Auction</th>
                    <?php if ($canViewFinance): ?><th>Expected Profit</th><?php endif; ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assessments as $assessment): ?>
                    <?php
                    $snapshot = json_decode((string) ($assessment['calculation_snapshot'] ?? ''), true) ?: [];
                    $profit = (float) ($snapshot['expected_profit_aud'] ?? 0);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $assessment['import_ref']) ?></td>
                        <td><?= htmlspecialchars(import_pipeline_vehicle_title($assessment)) ?></td>
                        <td><span class="badge <?= import_status_class(normalise_import_status((string) $assessment['status'])) ?>"><?= htmlspecialchars(normalise_import_status((string) $assessment['status'])) ?></span></td>
                        <td><?= htmlspecialchars((string) ($assessment['auction_house'] ?: 'TBC')) ?></td>
                        <?php if ($canViewFinance): ?><td class="<?= $profit >= 0 ? 'positive' : 'negative' ?>">$<?= number_format($profit, 2) ?></td><?php endif; ?>
                        <td><a class="btn secondary small-btn" href="import-calculator.php?id=<?= (int) $assessment['id'] ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$assessments): ?>
                    <tr><td colspan="<?= $canViewFinance ? 6 : 5 ?>">No import assessments yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
<?php require '../footer.php'; ?>

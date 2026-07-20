<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_view_imports');
require_once '../config/helpers.php';

$pageTitle = 'Japan Import Hub | CarFlip HQ';
$user = current_user();
$accessWhere = import_access_filter_sql('ia');
$assessments = $pdo->query("
    SELECT ia.*,
        creator.name AS creator_name,
        COALESCE((SELECT COUNT(*) FROM import_user_access iua WHERE iua.assessment_id = ia.id), 0) AS shared_count
    FROM import_assessments ia
    LEFT JOIN users creator ON creator.id = ia.created_by
    WHERE $accessWhere
    ORDER BY ia.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$canManageImports = user_can('can_manage_imports');
$canViewFinance = user_can('can_view_import_finance');

function import_status_class(?string $status): string
{
    return 'status-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $status)), '-');
}

require '../header.php';
?>
<div class="container imports-view">
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert success">Import assessment deleted.</div>
    <?php endif; ?>

    <div class="inventory-hero import-hero">
        <div>
            <div class="eyebrow">Japan Import Hub</div>
            <h1>Japanese Auction Pipeline</h1>
            <p>Assess auction sheets, calculate landed and on-road costs, and share import records without exposing private CarFlip HQ cars.</p>
        </div>
        <div class="inventory-count"><?= count($assessments) ?><span>assessments</span></div>
    </div>

    <div class="actions">
        <?php if ($canManageImports): ?>
        <a class="btn" href="import-calculator.php">+ New Assessment</a>
        <?php endif; ?>
    </div>

    <div class="car-card-grid section-title">
        <?php foreach ($assessments as $assessment): ?>
        <?php
        $snapshot = json_decode((string) ($assessment['calculation_snapshot'] ?? ''), true) ?: [];
        $totalCost = (float) ($snapshot['total_pre_sale_cost_aud'] ?? 0);
        $profit = (float) ($snapshot['expected_profit_aud'] ?? 0);
        ?>
        <article class="car-card import-card">
            <a class="car-card-media import-card-media" href="import-calculator.php?id=<?= (int) $assessment['id'] ?>">
                <span><?= htmlspecialchars(substr((string) ($assessment['make'] ?: 'I'), 0, 1)) ?></span>
            </a>
            <div class="car-card-body">
                <div class="card-title-row">
                    <h2><?= htmlspecialchars(trim($assessment['year'] . ' ' . $assessment['make'] . ' ' . $assessment['model'])) ?></h2>
                    <span class="badge <?= import_status_class($assessment['status'] ?? '') ?>"><?= htmlspecialchars((string) $assessment['status']) ?></span>
                </div>
                <div class="small"><?= htmlspecialchars($assessment['import_ref']) ?> / Lot <?= htmlspecialchars((string) ($assessment['lot_number'] ?: 'TBC')) ?></div>
                <div class="car-metrics">
                    <div><span>Hammer</span><b>¥<?= number_format((float) $assessment['hammer_price_jpy'], 0) ?></b></div>
                    <div><span>FOB AUD</span><b><?= $canViewFinance ? '$' . number_format((float) ($snapshot['fob_aud'] ?? 0), 2) : 'Restricted' ?></b></div>
                    <div><span>Total</span><b><?= $canViewFinance ? '$' . number_format($totalCost, 2) : 'Restricted' ?></b></div>
                    <div><span>Profit</span><b class="<?= $profit >= 0 ? 'positive' : 'negative' ?>"><?= $canViewFinance ? '$' . number_format($profit, 2) : 'Restricted' ?></b></div>
                </div>
                <div class="card-title-row">
                    <span class="small"><?= htmlspecialchars((string) ($assessment['auction_house'] ?: 'Auction TBC')) ?><?= $assessment['shared_count'] ? ' / shared with ' . (int) $assessment['shared_count'] : '' ?></span>
                    <div class="row-actions">
                        <a class="btn secondary small-btn" href="import-calculator.php?id=<?= (int) $assessment['id'] ?>">Open</a>
                        <?php if ($canManageImports): ?>
                        <form action="../actions/delete-import-assessment.php" method="POST" onsubmit="return confirm('Delete this import assessment? This cannot be undone.');">
                            <input type="hidden" name="id" value="<?= (int) $assessment['id'] ?>">
                            <button class="btn danger small-btn" type="submit">Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if (!$assessments): ?>
        <div class="empty-state">No import assessments yet.</div>
    <?php endif; ?>
</div>
<?php require '../footer.php'; ?>

<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_view_imports');
require_once '../config/helpers.php';
require_once '../config/import-status.php';

$pageTitle = 'Japan Import Hub | CarFlip HQ';
$user = current_user();
$accessWhere = import_access_filter_sql('ia');
$assessments = $pdo->query("
    SELECT ia.*,
        creator.name AS creator_name,
        approved.fob_low AS approved_fob_low,
        approved.fob_high AS approved_fob_high,
        approved.aggregate_fob_low AS aggregate_fob_low,
        approved.aggregate_fob_high AS aggregate_fob_high,
        approved.shipping_low AS approved_shipping_low,
        approved.shipping_high AS approved_shipping_high
    FROM import_assessments ia
    LEFT JOIN users creator ON creator.id = ia.created_by
    LEFT JOIN (
        SELECT latest_items.assessment_id,
            SUM(CASE WHEN latest_items.cost_code LIKE 'JP\\_%' THEN latest_items.low_estimate ELSE 0 END) AS fob_low,
            SUM(CASE WHEN latest_items.cost_code LIKE 'JP\\_%' THEN latest_items.high_estimate ELSE 0 END) AS fob_high,
            SUM(CASE WHEN latest_items.cost_code = 'JP_APPROVED_FOB' THEN latest_items.low_estimate ELSE 0 END) AS aggregate_fob_low,
            SUM(CASE WHEN latest_items.cost_code = 'JP_APPROVED_FOB' THEN latest_items.high_estimate ELSE 0 END) AS aggregate_fob_high,
            SUM(CASE WHEN latest_items.cost_code LIKE 'SHIP\\_%' THEN latest_items.low_estimate ELSE 0 END) AS shipping_low,
            SUM(CASE WHEN latest_items.cost_code LIKE 'SHIP\\_%' THEN latest_items.high_estimate ELSE 0 END) AS shipping_high
        FROM import_cost_items latest_items
        JOIN (
            SELECT assessment_id, cost_code, MAX(id) AS latest_id
            FROM import_cost_items
            GROUP BY assessment_id, cost_code
        ) latest ON latest.latest_id = latest_items.id
        WHERE latest_items.treatment NOT IN ('Included Elsewhere', 'Not Applicable')
            AND latest_items.status NOT IN ('Included Elsewhere', 'Not Applicable')
        GROUP BY latest_items.assessment_id
    ) approved ON approved.assessment_id = ia.id
    WHERE ia.archived_at IS NULL AND $accessWhere
    ORDER BY ia.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$canManageImports = user_can('can_manage_imports');
$canViewFinance = user_can('can_view_import_finance');

require '../header.php';
?>
<div class="container imports-view">
    <?php if (isset($_GET['archived'])): ?>
        <div class="alert success">Import assessment archived. History is preserved.</div>
    <?php endif; ?>

    <div class="inventory-hero import-hero">
        <div>
            <div class="eyebrow">Japan Import Hub</div>
            <h1>Japanese Auction Pipeline</h1>
            <p>Assess auction sheets, calculate landed and on-road costs, and manage Japan import opportunities from one place.</p>
        </div>
        <div class="inventory-count"><?= count($assessments) ?><span>assessments</span></div>
    </div>

    <div class="actions">
        <a class="btn secondary" href="import-pipeline.php">Pipeline</a>
        <?php if ($canManageImports): ?>
        <a class="btn" href="import-calculator.php">+ New Assessment</a>
        <?php endif; ?>
    </div>

    <div class="car-card-grid section-title">
        <?php foreach ($assessments as $assessment): ?>
        <?php
        $snapshot = json_decode((string) ($assessment['calculation_snapshot'] ?? ''), true) ?: [];
        $approvedFobLow = (float) ($assessment['approved_fob_low'] ?? 0);
        $approvedFobHigh = (float) ($assessment['approved_fob_high'] ?? 0);
        $aggregateFobLow = (float) ($assessment['aggregate_fob_low'] ?? 0);
        $aggregateFobHigh = (float) ($assessment['aggregate_fob_high'] ?? 0);
        if ($aggregateFobLow > 0 || $aggregateFobHigh > 0) {
            $approvedFobLow = $aggregateFobLow;
            $approvedFobHigh = $aggregateFobHigh;
        }
        $approvedShippingLow = (float) ($assessment['approved_shipping_low'] ?? 0);
        $approvedShippingHigh = (float) ($assessment['approved_shipping_high'] ?? 0);
        $approvedFob = ($approvedFobLow > 0 || $approvedFobHigh > 0) ? (($approvedFobLow + ($approvedFobHigh ?: $approvedFobLow)) / 2) : null;
        $approvedShipping = ($approvedShippingLow > 0 || $approvedShippingHigh > 0) ? (($approvedShippingLow + ($approvedShippingHigh ?: $approvedShippingLow)) / 2) : null;
        $fobAud = $approvedFob ?? (float) ($snapshot['fob_aud'] ?? 0);
        $totalCost = (float) ($snapshot['total_pre_sale_cost_aud'] ?? 0);
        if ($approvedFob !== null || $approvedShipping !== null) {
            $snapshotFob = (float) ($snapshot['fob_aud'] ?? 0);
            $snapshotShipping = (float) ($assessment['ocean_freight_aud'] ?? 0) + (float) ($assessment['marine_insurance_aud'] ?? 0);
            $totalCost = $totalCost - $snapshotFob - $snapshotShipping + $fobAud + ($approvedShipping ?? $snapshotShipping);
        }
        $profit = (float) ($assessment['expected_sale_price_aud'] ?? 0) > 0 ? (float) $assessment['expected_sale_price_aud'] - $totalCost : (float) ($snapshot['expected_profit_aud'] ?? 0);
        ?>
        <article class="car-card import-card">
            <a class="car-card-media import-card-media" href="import-calculator.php?id=<?= (int) $assessment['id'] ?>">
                <span><?= htmlspecialchars(substr((string) ($assessment['make'] ?: 'I'), 0, 1)) ?></span>
            </a>
            <div class="car-card-body">
                <div class="card-title-row">
                    <h2><?= htmlspecialchars(trim($assessment['year'] . ' ' . $assessment['make'] . ' ' . $assessment['model'])) ?></h2>
                    <span class="badge <?= import_status_class($assessment['status'] ?? '') ?>"><?= htmlspecialchars(normalise_import_status((string) $assessment['status'])) ?></span>
                </div>
                <div class="small"><?= htmlspecialchars($assessment['import_ref']) ?> / Lot <?= htmlspecialchars((string) ($assessment['lot_number'] ?: 'TBC')) ?></div>
                <div class="car-metrics">
                    <div><span>Hammer</span><b>¥<?= number_format((float) $assessment['hammer_price_jpy'], 0) ?></b></div>
                    <div><span><?= $approvedFob !== null ? 'Approved FOB' : 'FOB Estimate' ?></span><b><?= $canViewFinance ? '$' . number_format($fobAud, 2) : 'Restricted' ?></b></div>
                    <div><span>Total</span><b><?= $canViewFinance ? '$' . number_format($totalCost, 2) : 'Restricted' ?></b></div>
                    <div><span>Profit</span><b class="<?= $profit >= 0 ? 'positive' : 'negative' ?>"><?= $canViewFinance ? '$' . number_format($profit, 2) : 'Restricted' ?></b></div>
                </div>
                <div class="card-title-row">
                    <span class="small"><?= htmlspecialchars((string) ($assessment['auction_house'] ?: 'Auction TBC')) ?></span>
                    <div class="row-actions">
                        <a class="btn secondary small-btn" href="import-calculator.php?id=<?= (int) $assessment['id'] ?>">Open</a>
                        <?php if ($canManageImports): ?>
                        <form action="../actions/delete-import-assessment.php" method="POST" onsubmit="return confirm('Archive this import assessment? History will be preserved.');">
                            <input type="hidden" name="id" value="<?= (int) $assessment['id'] ?>">
                            <button class="btn danger small-btn" type="submit">Archive</button>
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

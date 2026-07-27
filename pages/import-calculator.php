<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_view_imports');
require_once '../config/helpers.php';
require_once '../config/import-status.php';
require_once '../config/import-costs.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$canManageImports = user_can('can_manage_imports');
$canViewFinance = user_can('can_view_import_finance');
$user = current_user();
$assessment = null;
$saveError = trim((string) ($_GET['save_error'] ?? ''));
$saved = isset($_GET['saved']);
$statusUpdated = isset($_GET['status_updated']);
$reportApproved = isset($_GET['report_approved']);
$auditRows = [];
$reportRows = [];
$documentRows = [];
$costRows = [];
$costSummary = null;
$approvedFobRows = [];
$aggregateFobRows = [];
$approvedShippingRows = [];
$approvedFobFromCif = false;
$approvedCostSummary = [
    'has_fob' => false,
    'fob_low' => 0.0,
    'fob_high' => 0.0,
    'has_shipping' => false,
    'shipping_low' => 0.0,
    'shipping_high' => 0.0,
    'has_cif' => false,
    'cif_low' => 0.0,
    'cif_high' => 0.0,
];

if ($id) {
    require_import_assessment($pdo, $id);
    $stmt = $pdo->prepare('SELECT * FROM import_assessments WHERE id = ?');
    $stmt->execute([$id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

    $auditStmt = $pdo->prepare("
        SELECT ial.*, users.name AS user_name
        FROM import_audit_log ial
        LEFT JOIN users ON users.id = ial.user_id
        WHERE ial.assessment_id = ?
        ORDER BY ial.created_at DESC, ial.id DESC
        LIMIT 30
    ");
    $auditStmt->execute([$id]);
    $auditRows = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

    $reportStmt = $pdo->prepare('
        SELECT icr.*, idoc.original_filename, idoc.stored_path
        FROM import_cost_reports icr
        JOIN import_documents idoc ON idoc.id = icr.document_id
        WHERE icr.assessment_id = ?
        ORDER BY icr.imported_at DESC, icr.id DESC
    ');
    $reportStmt->execute([$id]);
    $reportRows = $reportStmt->fetchAll(PDO::FETCH_ASSOC);

    $documentStmt = $pdo->prepare('
        SELECT *
        FROM import_documents
        WHERE assessment_id = ? AND archived_at IS NULL
        ORDER BY created_at DESC, id DESC
    ');
    $documentStmt->execute([$id]);
    $documentRows = $documentStmt->fetchAll(PDO::FETCH_ASSOC);

    $costStmt = $pdo->prepare('
        SELECT ici.*
        FROM import_cost_items ici
        JOIN (
            SELECT cost_code, MAX(id) AS latest_id
            FROM import_cost_items
            WHERE assessment_id = ?
            GROUP BY cost_code
        ) latest ON latest.latest_id = ici.id
        ORDER BY FIELD(ici.category, "Japan Purchase", "Japan Logistics", "Export", "Shipping/CIF", "Melbourne/Australia", "Final Landed Cost"), ici.id
    ');
    $costStmt->execute([$id]);
    $costRows = $costStmt->fetchAll(PDO::FETCH_ASSOC);
    $costSummary = import_calculate_cost_summary($costRows);
    foreach ($costRows as $costRow) {
        if ((string) $costRow['cost_code'] === 'JP_APPROVED_FOB') {
            $aggregateFobRows[] = $costRow;
        }
        if (in_array((string) $costRow['cost_code'], ['JP_PURCHASE', 'JP_AUCTION_AGENT_EXPORT', 'JP_INLAND_TRANSPORT', 'JP_EXPORT_YARD_HANDLING', 'JP_APPROVED_FOB'], true)) {
            $approvedFobRows[] = $costRow;
        }
        if (str_starts_with((string) $costRow['cost_code'], 'SHIP_')) {
            $approvedShippingRows[] = $costRow;
        }
    }
    $approvedCostSummary = [
        'has_fob' => !empty($costRows) && ((float) ($costSummary['fob_high'] ?? 0) > 0 || (float) ($costSummary['fob_low'] ?? 0) > 0),
        'fob_low' => (float) ($costSummary['fob_low'] ?? 0),
        'fob_high' => (float) ($costSummary['fob_high'] ?? 0),
        'has_shipping' => !empty($costRows) && ((float) ($costSummary['shipping_high'] ?? 0) > 0 || (float) ($costSummary['shipping_low'] ?? 0) > 0),
        'shipping_low' => (float) ($costSummary['shipping_low'] ?? 0),
        'shipping_high' => (float) ($costSummary['shipping_high'] ?? 0),
        'has_cif' => !empty($costRows) && ((float) ($costSummary['cif_high'] ?? 0) > 0 || (float) ($costSummary['cif_low'] ?? 0) > 0),
        'cif_low' => (float) ($costSummary['cif_low'] ?? 0),
        'cif_high' => (float) ($costSummary['cif_high'] ?? 0),
    ];
    if ($aggregateFobRows) {
        $approvedFobRows = $aggregateFobRows;
        $approvedFobFromCif = true;
    }
}

if (!$assessment && !$canManageImports) {
    http_response_code(403);
    die('You do not have permission to create import assessments.');
}

$settings = $pdo->query('SELECT setting_key, setting_value FROM import_settings')->fetchAll(PDO::FETCH_KEY_PAIR);

function import_value(?array $assessment, array $settings, string $field, $fallback = ''): string
{
    if ($assessment && array_key_exists($field, $assessment) && $assessment[$field] !== null) {
        return (string) $assessment[$field];
    }

    if (array_key_exists($field, $settings)) {
        return (string) $settings[$field];
    }

    return (string) $fallback;
}

function import_date_value(?array $assessment, string $field): string
{
    return $assessment && !empty($assessment[$field]) ? (string) $assessment[$field] : '';
}

function import_money_range(float $low, float $high): string
{
    if (abs($high - $low) < 0.01) {
        return '$' . number_format($low, 2);
    }

    return '$' . number_format($low, 2) . ' - $' . number_format($high, 2);
}

$pageTitle = ($assessment ? 'Edit Import Assessment' : 'New Import Assessment') . ' | CarFlip HQ';
require '../header.php';
?>
<div class="container import-calculator-view">
    <div class="page-heading">
        <div>
            <div class="eyebrow">Japan Import Hub</div>
            <h1><?= $assessment ? htmlspecialchars((string) $assessment['import_ref']) : 'Auction & Landed Cost Calculator' ?></h1>
            <p class="small">Build a clean import position from vehicle details, FOB, CIF/shipping, Melbourne on-road costs, and expected profit.</p>
        </div>
        <a class="btn secondary" href="imports.php">Back to Japan Hub</a>
    </div>

    <?php if (!$canViewFinance): ?>
        <div class="alert">Your account can use Japan import records, but landed-cost and profit fields are restricted.</div>
    <?php endif; ?>
    <?php if ($saveError !== ''): ?>
        <div class="alert error"><?= htmlspecialchars($saveError) ?></div>
    <?php elseif ($saved): ?>
        <div class="alert success">Import assessment saved.</div>
    <?php elseif ($statusUpdated): ?>
        <div class="alert success">Import status updated.</div>
    <?php elseif ($reportApproved): ?>
        <div class="alert success">Japan report approved and cost lines committed.</div>
    <?php endif; ?>

    <?php if ($assessment): ?>
        <section class="form-card import-section-card">
            <div class="section-kicker">Japan Reports</div>
            <h2>Upload Japan Report</h2>
            <p class="small">Upload FOB or CIF Excel reports. The original file is stored first, then values are reviewed before approval.</p>
            <?php if ($canManageImports): ?>
                <form class="inline-upload-form" action="<?= app_url('actions/upload-import-report.php') ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="assessment_id" value="<?= (int) $assessment['id'] ?>">
                    <input type="file" name="japan_report" accept=".xlsx,.xls" required>
                    <button class="btn" type="submit">Upload Japan Report</button>
                </form>
            <?php endif; ?>
            <?php if ($reportRows): ?>
                <div class="report-card-grid">
                    <?php foreach ($reportRows as $report): ?>
                        <div class="report-type-card">
                            <span><?= htmlspecialchars((string) $report['report_type']) ?></span>
                            <strong><?= htmlspecialchars((string) $report['approval_status']) ?></strong>
                            <small><?= htmlspecialchars((string) $report['original_filename']) ?></small>
                            <div class="row-actions">
                                <a class="btn secondary small-btn" href="review-import-report.php?id=<?= (int) $report['id'] ?>">Review</a>
                                <a class="btn secondary small-btn" href="<?= app_url('actions/download-import-document.php?id=' . (int) $report['document_id']) ?>">Download</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">No Japan reports uploaded yet.</div>
            <?php endif; ?>
        </section>

    <?php endif; ?>

    <form class="import-calculator" method="post" enctype="multipart/form-data" action="<?= app_url('actions/save-import-assessment.php') ?>">
        <?php if ($assessment): ?>
            <input type="hidden" name="id" value="<?= (int) $assessment['id'] ?>">
        <?php endif; ?>

        <?php if ($canViewFinance): ?>
        <div class="import-cost-flow" data-import-summary>
            <div class="flow-card">
                <span>FOB Cost</span>
                <strong data-output="fobAud">$0.00</strong>
                <small data-output="fobSource">Vehicle purchase plus Japan export costs</small>
            </div>
            <div class="flow-card">
                <span>Shipping / CIF Add-ons</span>
                <strong data-output="shippingAud">$0.00</strong>
                <small>Ocean freight, EBS, insurance, heat treatment</small>
            </div>
            <div class="flow-card">
                <span>CIF Before Melbourne</span>
                <strong data-output="cifAud">$0.00</strong>
                <small>FOB plus shipping before GST and local costs</small>
            </div>
            <div class="flow-card">
                <span>Melbourne & On-road</span>
                <strong data-output="melbourneAud">$0.00</strong>
                <small>Customs, compliance, RWC, rego, transport, GST</small>
            </div>
            <div class="flow-card primary">
                <span>Total Landed Cost</span>
                <strong data-output="totalCost">$0.00</strong>
                <small>Estimated all-in cost before sale</small>
            </div>
            <div class="flow-card profit-card">
                <span>Expected Profit</span>
                <strong data-output="profit">$0.00</strong>
                <small data-output="margin">0.0% margin</small>
            </div>
        </div>
        <div class="import-warnings" data-output="warnings"></div>
        <?php endif; ?>

        <?php if (user_can('can_use_ai')): ?>
        <section class="form-card import-section-card ai-import-card">
            <div class="section-kicker">AI Extract</div>
            <h2>Fill From Auction Sheet</h2>
            <p class="small">Upload a Japanese auction sheet image or paste a public auction/listing link. Review the extracted fields before saving.</p>
            <div class="ai-extract-grid">
                <div>
                    <label>Auction sheet image</label>
                    <input data-ai-image type="file" accept="image/*" capture="environment">
                </div>
                <div>
                    <label>Auction / listing link</label>
                    <input data-ai-url type="url" placeholder="https://...">
                </div>
                <div class="ai-extract-action">
                    <button class="btn" type="button" data-ai-extract>Extract Fields</button>
                </div>
            </div>
            <div class="ai-extract-status small" data-ai-status></div>
            <div class="ai-extract-preview" data-ai-preview hidden></div>
        </section>
        <?php endif; ?>

        <?php if ($canViewFinance): ?>
        <section class="form-card import-section-card japan-report-summary">
            <div class="section-kicker">Approved Report Data</div>
            <h2>Cost Trail</h2>
            <p class="small">Use uploaded FOB and CIF reports as the source of truth. Melbourne costs are added separately below.</p>
            <?php if ($costRows): ?>
                <div class="cost-trail">
                    <div>
                        <span>FOB from Japan report</span>
                        <strong><?= import_money_range((float) $approvedCostSummary['fob_low'], (float) $approvedCostSummary['fob_high']) ?></strong>
                    </div>
                    <div>
                        <span>Shipping/CIF add-ons</span>
                        <strong><?= import_money_range((float) $approvedCostSummary['shipping_low'], (float) $approvedCostSummary['shipping_high']) ?></strong>
                    </div>
                    <div>
                        <span>CIF before Melbourne</span>
                        <strong><?= import_money_range((float) $approvedCostSummary['cif_low'], (float) $approvedCostSummary['cif_high']) ?></strong>
                    </div>
                </div>
                <details class="report-lines">
                    <summary>Review approved line items</summary>
                    <div class="table-wrap cost-summary-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Stage</th>
                                    <th>Cost Item</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($costRows as $row): ?>
                                <?php [$displayLow, $displayHigh] = import_cost_item_estimates($row); ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $row['category']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars((string) $row['description']) ?></strong>
                                        <?php if (!empty($row['source_label'])): ?>
                                            <small><?= htmlspecialchars((string) $row['source_label']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= import_money_range($displayLow, $displayHigh) ?></td>
                                    <td><span class="badge"><?= htmlspecialchars((string) $row['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php else: ?>
                <div class="empty-state">No approved report data yet. Upload and approve FOB/CIF reports when you have them.</div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <div class="import-layout">
            <section class="form-card import-section-card">
                <div class="section-kicker">Step 1</div>
                <h2>Vehicle & Auction</h2>
                <p class="small">Start with the car identity and auction reference.</p>
                <div class="form-grid two">
                    <div><label>Make</label><input name="make" value="<?= htmlspecialchars(import_value($assessment, $settings, 'make')) ?>" required></div>
                    <div><label>Model</label><input name="model" value="<?= htmlspecialchars(import_value($assessment, $settings, 'model')) ?>" required></div>
                    <div><label>Variant</label><input name="variant" value="<?= htmlspecialchars(import_value($assessment, $settings, 'variant')) ?>"></div>
                    <div><label>Year</label><input type="number" name="year" value="<?= htmlspecialchars(import_value($assessment, $settings, 'year')) ?>"></div>
                    <div><label>Chassis / VIN</label><input name="chassis_vin" value="<?= htmlspecialchars(import_value($assessment, $settings, 'chassis_vin')) ?>"></div>
                    <div><label>Mileage</label><input type="number" name="mileage" value="<?= htmlspecialchars(import_value($assessment, $settings, 'mileage')) ?>"></div>
                    <div><label>Auction House</label><input name="auction_house" value="<?= htmlspecialchars(import_value($assessment, $settings, 'auction_house')) ?>"></div>
                    <div><label>Auction Date</label><input type="date" name="auction_date" value="<?= htmlspecialchars(import_date_value($assessment, 'auction_date')) ?>"></div>
                    <div><label>Auction Grade</label><input name="auction_grade" value="<?= htmlspecialchars(import_value($assessment, $settings, 'auction_grade')) ?>"></div>
                    <div><label>Interior Grade</label><input name="interior_grade" value="<?= htmlspecialchars(import_value($assessment, $settings, 'interior_grade')) ?>"></div>
                    <div><label>Lot Number</label><input name="lot_number" value="<?= htmlspecialchars(import_value($assessment, $settings, 'lot_number')) ?>"></div>
                    <div><label>Japan Agent</label><input name="japan_agent" value="<?= htmlspecialchars(import_value($assessment, $settings, 'japan_agent')) ?>"></div>
                </div>
                <label>Status</label>
                <select name="status" <?= $canManageImports ? '' : 'disabled' ?>>
                    <?php $currentStatus = normalise_import_status(import_value($assessment, $settings, 'status', 'Under Assessment')); ?>
                    <?php foreach (import_status_steps() as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= $currentStatus === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </section>

            <section class="form-card import-section-card">
                <div class="section-kicker">Step 2</div>
                <h2>Japan Purchase & FOB</h2>
                <p class="small">FOB is the vehicle purchase plus Japan export costs before shipping to Australia.</p>
                <?php if ($canViewFinance && $approvedFobRows): ?>
                    <div class="approved-fob-panel">
                        <div class="approved-fob-head">
                            <div>
                                <strong><?= $approvedFobFromCif ? 'Approved FOB Total' : 'Approved FOB Report' ?></strong>
                                <small><?= $approvedFobFromCif ? 'This FOB total was brought down inside the CIF Budget Report.' : 'These values came from the uploaded FOB Budget Report.' ?></small>
                            </div>
                            <div class="approved-fob-total">
                                <span>Total Estimated FOB</span>
                                <b><?= import_money_range((float) ($approvedCostSummary['fob_low'] ?? 0), (float) ($approvedCostSummary['fob_high'] ?? 0)) ?></b>
                            </div>
                        </div>
                        <div class="approved-fob-grid">
                            <?php foreach ($approvedFobRows as $fobRow): ?>
                                <?php [$displayLow, $displayHigh] = import_cost_item_estimates($fobRow); ?>
                                <div>
                                    <span><?= htmlspecialchars((string) $fobRow['description']) ?></span>
                                    <b><?= import_money_range($displayLow, $displayHigh) ?></b>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <details class="manual-cost-details" <?= $approvedFobRows ? '' : 'open' ?>>
                    <summary>Manual auction estimate</summary>
                    <p class="small">Use this when there is no approved FOB report yet. If a report is approved, the report total is used for the main calculation.</p>
                <div class="form-grid two">
                    <div class="exchange-rate-field">
                        <div class="field-label-row">
                            <label>Exchange Rate</label>
                            <button class="text-button" type="button" data-live-rate>Use live rate</button>
                        </div>
                        <input data-calc data-exchange-rate type="number" step="0.0001" min="0" name="exchange_rate" value="<?= htmlspecialchars(import_value($assessment, $settings, 'exchange_rate')) ?>" placeholder="JPY per AUD">
                        <small data-live-rate-status>Live AUD/JPY rate will load here.</small>
                    </div>
                    <div><label>Hammer Price JPY</label><input data-calc type="number" step="1" min="0" name="hammer_price_jpy" value="<?= htmlspecialchars(import_value($assessment, $settings, 'hammer_price_jpy')) ?>"></div>
                    <div><label>Auction Fee JPY</label><input data-calc type="number" step="1" min="0" name="auction_fee_jpy" value="<?= htmlspecialchars(import_value($assessment, $settings, 'auction_fee_jpy')) ?>"></div>
                    <div><label>Japan Agent Fee JPY</label><input data-calc type="number" step="1" min="0" name="japan_agent_fee_jpy" value="<?= htmlspecialchars(import_value($assessment, $settings, 'japan_agent_fee_jpy')) ?>"></div>
                    <div><label>Inland Transport JPY</label><input data-calc type="number" step="1" min="0" name="inland_transport_jpy" value="<?= htmlspecialchars(import_value($assessment, $settings, 'inland_transport_jpy')) ?>"></div>
                    <div><label>Export Docs JPY</label><input data-calc type="number" step="1" min="0" name="export_docs_jpy" value="<?= htmlspecialchars(import_value($assessment, $settings, 'export_docs_jpy')) ?>"></div>
                    <div><label>Japan Port Fees JPY</label><input data-calc type="number" step="1" min="0" name="japan_port_fees_jpy" value="<?= htmlspecialchars(import_value($assessment, $settings, 'japan_port_fees_jpy')) ?>"></div>
                    <div><label>Other Japan Costs JPY</label><input data-calc type="number" step="1" min="0" name="other_japan_costs_jpy" value="<?= htmlspecialchars(import_value($assessment, $settings, 'other_japan_costs_jpy')) ?>"></div>
                </div>
                <label>Other Japan Cost Notes</label><input name="other_japan_costs_notes" value="<?= htmlspecialchars(import_value($assessment, $settings, 'other_japan_costs_notes')) ?>">
                </details>
            </section>

            <?php if ($canViewFinance): ?>
            <section class="form-card import-section-card finance-card">
                <div class="section-kicker">Step 3</div>
                <h2>Shipping & CIF</h2>
                <p class="small">CIF is FOB plus freight, insurance, and shipping surcharges before Melbourne costs.</p>
                <?php if ($approvedShippingRows): ?>
                    <div class="approved-fob-panel">
                        <div class="approved-fob-head">
                            <div>
                                <strong>Approved CIF Report</strong>
                                <small>FOB is brought down from the report, then shipping add-ons are added to reach CIF.</small>
                            </div>
                            <div class="approved-fob-total">
                                <span>Total CIF Before Melbourne</span>
                                <b><?= import_money_range((float) ($approvedCostSummary['cif_low'] ?? 0), (float) ($approvedCostSummary['cif_high'] ?? 0)) ?></b>
                            </div>
                        </div>
                        <div class="approved-fob-grid">
                            <div>
                                <span>FOB Cost</span>
                                <b><?= import_money_range((float) ($approvedCostSummary['fob_low'] ?? 0), (float) ($approvedCostSummary['fob_high'] ?? 0)) ?></b>
                            </div>
                            <div>
                                <span>Shipping Add-ons</span>
                                <b><?= import_money_range((float) ($approvedCostSummary['shipping_low'] ?? 0), (float) ($approvedCostSummary['shipping_high'] ?? 0)) ?></b>
                            </div>
                            <?php foreach ($approvedShippingRows as $shippingRow): ?>
                                <?php [$displayLow, $displayHigh] = import_cost_item_estimates($shippingRow); ?>
                                <div>
                                    <span><?= htmlspecialchars((string) $shippingRow['description']) ?></span>
                                    <b><?= import_money_range($displayLow, $displayHigh) ?></b>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="form-grid two">
                    <div><label>Ocean Freight AUD</label><input data-calc type="number" step="0.01" min="0" name="ocean_freight_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'ocean_freight_aud')) ?>"></div>
                    <div><label>Marine Insurance AUD</label><input data-calc type="number" step="0.01" min="0" name="marine_insurance_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'marine_insurance_aud')) ?>"></div>
                </div>
            </section>

            <section class="form-card import-section-card finance-card">
                <div class="section-kicker">Step 4</div>
                <h2>Melbourne & Sale Position</h2>
                <p class="small">Add the remaining local costs after CIF, then compare the all-in cost against expected sale.</p>
                <div class="form-grid two">
                    <div><label>Expected Sale AUD</label><input data-calc type="number" step="0.01" min="0" name="expected_sale_price_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'expected_sale_price_aud')) ?>"></div>
                    <div><label>Target Profit AUD</label><input data-calc type="number" step="0.01" min="0" name="target_profit_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'target_profit_aud')) ?>"></div>
                    <div><label>Port Charges</label><input data-calc type="number" step="0.01" min="0" name="port_charges_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'port_charges_aud')) ?>"></div>
                    <div><label>Customs Broker</label><input data-calc type="number" step="0.01" min="0" name="customs_broker_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'customs_broker_aud')) ?>"></div>
                    <div><label>Biosecurity</label><input data-calc type="number" step="0.01" min="0" name="biosecurity_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'biosecurity_aud')) ?>"></div>
                    <div><label>Port Transport</label><input data-calc type="number" step="0.01" min="0" name="port_transport_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'port_transport_aud')) ?>"></div>
                    <div><label>Compliance</label><input data-calc type="number" step="0.01" min="0" name="compliance_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'compliance_aud')) ?>"></div>
                    <div><label>Registration</label><input data-calc type="number" step="0.01" min="0" name="registration_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'registration_aud')) ?>"></div>
                    <div><label>Duty Rate</label><input data-calc type="number" step="0.0001" min="0" name="duty_rate" value="<?= htmlspecialchars(import_value($assessment, $settings, 'duty_rate')) ?>"></div>
                    <div><label>Manual Duty AUD</label><input data-calc type="number" step="0.01" min="0" name="duty_manual_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'duty_manual_aud')) ?>"></div>
                    <div><label>GST Rate</label><input data-calc type="number" step="0.0001" min="0" name="gst_rate" value="<?= htmlspecialchars(import_value($assessment, $settings, 'gst_rate', '0.10')) ?>"></div>
                    <div><label>Other Australia Costs</label><input data-calc type="number" step="0.01" min="0" name="other_australia_costs_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'other_australia_costs_aud')) ?>"></div>
                </div>
                <label>Other Australia Cost Notes</label><input name="other_australia_costs_notes" value="<?= htmlspecialchars(import_value($assessment, $settings, 'other_australia_costs_notes')) ?>">
            </section>
            <?php endif; ?>
        </div>

        <section class="form-card section-title import-section-card">
            <div class="section-kicker">Review</div>
            <h2>Notes & Calculation</h2>
            <label>Notes</label>
            <textarea name="notes" rows="4"><?= htmlspecialchars(import_value($assessment, $settings, 'notes')) ?></textarea>
            <?php if ($canViewFinance): ?>
            <details class="calc-details" open>
                <summary>Show calculation</summary>
                <pre data-output="formula"></pre>
            </details>
            <?php endif; ?>
        </section>

        <?php if ($canManageImports): ?>
        <div class="actions">
            <button class="btn" type="submit" data-save-assessment><?= $assessment ? 'Save Assessment' : 'Create Assessment' ?></button>
        </div>
        <?php endif; ?>
    </form>

    <?php if ($assessment): ?>
    <section class="form-card section-title import-section-card">
        <div class="section-kicker">History</div>
        <h2>Status & Activity</h2>
        <?php if ($auditRows): ?>
            <div class="audit-list">
                <?php foreach ($auditRows as $row): ?>
                    <div class="audit-item">
                        <div>
                            <strong><?= htmlspecialchars(str_replace('_', ' ', ucwords((string) $row['action'], '_'))) ?></strong>
                            <p><?= htmlspecialchars((string) $row['details']) ?></p>
                        </div>
                        <span><?= htmlspecialchars((string) ($row['user_name'] ?: 'System')) ?><br><?= htmlspecialchars((string) $row['created_at']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">No activity recorded yet.</div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</div>

<script>
(() => {
    const form = document.querySelector('.import-calculator');
    const saveButton = document.querySelector('[data-save-assessment]');
    if (!form || !saveButton) return;

    form.addEventListener('submit', () => {
        saveButton.disabled = true;
        saveButton.textContent = saveButton.textContent.includes('Save') ? 'Saving...' : 'Creating...';
    });
})();
</script>

<?php if ($canViewFinance): ?>
<script>
(() => {
    const form = document.querySelector('.import-calculator');
    if (!form) return;
    const money = new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' });
    const yen = new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY', maximumFractionDigits: 0 });
    const minimumProfit = <?= json_encode((float) ($settings['minimum_profit_aud'] ?? 2000)) ?>;
    const approvedCosts = <?= json_encode($approvedCostSummary) ?>;
    const fields = [...form.querySelectorAll('[data-calc]')];
    const rateInput = form.querySelector('[data-exchange-rate]');
    const liveRateButton = form.querySelector('[data-live-rate]');
    const liveRateStatus = form.querySelector('[data-live-rate-status]');
    const get = name => Number(form.elements[name]?.value || 0);
    const set = (key, value) => {
        const el = form.querySelector(`[data-output="${key}"]`);
        if (el) el.textContent = value;
    };
    const setHtml = (key, value) => {
        const el = form.querySelector(`[data-output="${key}"]`);
        if (el) el.innerHTML = value;
    };

    function calculate() {
        const rate = get('exchange_rate');
        const hammerJpy = get('hammer_price_jpy');
        const japanFees = get('auction_fee_jpy') + get('japan_agent_fee_jpy') + get('inland_transport_jpy') + get('export_docs_jpy') + get('japan_port_fees_jpy') + get('other_japan_costs_jpy');
        const manualFobJpy = hammerJpy + japanFees;
        const manualFobAud = rate > 0 ? manualFobJpy / rate : 0;
        const approvedFobLow = Number(approvedCosts.fob_low || 0);
        const approvedFobHigh = Number(approvedCosts.fob_high || approvedFobLow);
        const hasApprovedFob = Boolean(approvedCosts.has_fob && (approvedFobLow > 0 || approvedFobHigh > 0));
        const fobAud = hasApprovedFob ? ((approvedFobLow + approvedFobHigh) / 2) : manualFobAud;
        const manualShippingAud = get('ocean_freight_aud') + get('marine_insurance_aud');
        const approvedShippingLow = Number(approvedCosts.shipping_low || 0);
        const approvedShippingHigh = Number(approvedCosts.shipping_high || approvedShippingLow);
        const hasApprovedShipping = Boolean(approvedCosts.has_shipping && (approvedShippingLow > 0 || approvedShippingHigh > 0));
        const shippingAud = hasApprovedShipping ? ((approvedShippingLow + approvedShippingHigh) / 2) : manualShippingAud;
        const approvedCifLow = Number(approvedCosts.cif_low || 0);
        const approvedCifHigh = Number(approvedCosts.cif_high || approvedCifLow);
        const hasApprovedCif = Boolean(approvedCosts.has_cif && (approvedCifLow > 0 || approvedCifHigh > 0));
        const cifAud = hasApprovedCif ? ((approvedCifLow + approvedCifHigh) / 2) : fobAud + shippingAud;
        const duty = get('duty_manual_aud') > 0 ? get('duty_manual_aud') : fobAud * get('duty_rate');
        const gstBase = cifAud + duty;
        const gst = gstBase * get('gst_rate');
        const melbourneCosts = get('port_charges_aud') + get('customs_broker_aud') + get('biosecurity_aud') + get('port_transport_aud') + get('compliance_aud') + get('registration_aud') + duty + gst + get('other_australia_costs_aud');
        const total = cifAud + melbourneCosts;
        const sale = get('expected_sale_price_aud');
        const profit = sale - total;
        const margin = sale > 0 ? (profit / sale) * 100 : 0;
        const warnings = [];
        const manualFobNeedsRate = !hasApprovedFob && (hammerJpy > 0 || japanFees > 0);

        if (manualFobNeedsRate && rate <= 0) warnings.push('Exchange rate is required for manual JPY auction inputs.');
        if (sale > 0 && profit < minimumProfit) warnings.push(`Expected profit is below ${money.format(minimumProfit)}.`);

        set('fobAud', hasApprovedFob && Math.abs(approvedFobHigh - approvedFobLow) > 0.01 ? `${money.format(approvedFobLow)} - ${money.format(approvedFobHigh)}` : money.format(fobAud));
        set('fobSource', hasApprovedFob ? 'Approved FOB report total' : `${yen.format(hammerJpy)} purchase + ${yen.format(japanFees)} Japan fees`);
        set('shippingAud', hasApprovedShipping && Math.abs(approvedShippingHigh - approvedShippingLow) > 0.01 ? `${money.format(approvedShippingLow)} - ${money.format(approvedShippingHigh)}` : money.format(shippingAud));
        set('cifAud', hasApprovedCif && Math.abs(approvedCifHigh - approvedCifLow) > 0.01 ? `${money.format(approvedCifLow)} - ${money.format(approvedCifHigh)}` : money.format(cifAud));
        set('melbourneAud', money.format(melbourneCosts));
        set('totalCost', money.format(total));
        set('profit', money.format(profit));
        set('margin', `${margin.toFixed(1)}% margin`);
        setHtml('warnings', warnings.map(w => `<div class="alert warning">${w}</div>`).join(''));
        set('formula', [
            `Japan-side fees: ${yen.format(japanFees)}`,
            hasApprovedFob ? `FOB AUD: approved report range = ${money.format(approvedFobLow)} - ${money.format(approvedFobHigh)}` : `FOB AUD: (${yen.format(manualFobJpy)}) / exchange rate = ${money.format(fobAud)}`,
            hasApprovedShipping ? `Shipping/CIF add-ons: approved report range = ${money.format(approvedShippingLow)} - ${money.format(approvedShippingHigh)}, midpoint used = ${money.format(shippingAud)}` : `Shipping/CIF add-ons: ocean freight + marine insurance = ${money.format(manualShippingAud)}`,
            hasApprovedCif ? `CIF before Melbourne: approved report range = ${money.format(approvedCifLow)} - ${money.format(approvedCifHigh)}, midpoint used = ${money.format(cifAud)}` : `CIF before Melbourne: FOB + shipping/CIF add-ons = ${money.format(cifAud)}`,
            `Duty estimate: ${money.format(duty)}`,
            `GST base: CIF before Melbourne + duty = ${money.format(gstBase)}`,
            `GST estimate: GST base x GST rate = ${money.format(gst)}`,
            `Melbourne & on-road costs: local charges + duty + GST = ${money.format(melbourneCosts)}`,
            `Total landed cost: CIF before Melbourne + Melbourne & on-road costs = ${money.format(total)}`,
            `Expected profit: expected sale - total landed cost = ${money.format(profit)}`
        ].join('\n'));
    }

    fields.forEach(field => field.addEventListener('input', calculate));

    async function loadLiveRate(shouldApply = false) {
        if (!rateInput || !liveRateStatus) return;

        liveRateStatus.textContent = 'Checking live AUD/JPY...';
        try {
            const response = await fetch('<?= app_url('actions/live-exchange-rate.php') ?>', { cache: 'no-store' });
            if (!response.ok) throw new Error('Rate service unavailable');
            const data = await response.json();
            const rate = Number(data?.rate || data?.rates?.JPY || 0);
            if (!rate) throw new Error('No AUD/JPY rate returned');

            const formattedRate = rate.toFixed(4);
            liveRateStatus.textContent = `Live AUD/JPY: ${formattedRate}${data?.date ? ` (${data.date})` : ''}`;
            if (shouldApply || !rateInput.value) {
                rateInput.value = formattedRate;
                rateInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } catch (error) {
            liveRateStatus.textContent = 'Live rate unavailable. Enter the auction rate manually.';
        }
    }

    if (liveRateButton) {
        liveRateButton.addEventListener('click', () => loadLiveRate(true));
    }

    loadLiveRate(false);
    calculate();
})();
</script>
<?php endif; ?>
<?php if (user_can('can_use_ai')): ?>
<script>
(() => {
    const form = document.querySelector('.import-calculator');
    const button = document.querySelector('[data-ai-extract]');
    if (!form || !button) return;

    const imageInput = document.querySelector('[data-ai-image]');
    const urlInput = document.querySelector('[data-ai-url]');
    const status = document.querySelector('[data-ai-status]');
    const preview = document.querySelector('[data-ai-preview]');
    const labels = {
        make: 'Make',
        model: 'Model',
        variant: 'Variant',
        year: 'Year',
        chassis_vin: 'Chassis / VIN',
        mileage: 'Mileage',
        auction_house: 'Auction House',
        auction_date: 'Auction Date',
        auction_grade: 'Auction Grade',
        interior_grade: 'Interior Grade',
        lot_number: 'Lot Number',
        japan_agent: 'Japan Agent',
        notes: 'Notes'
    };

    function setStatus(message, type = '') {
        if (!status) return;
        status.textContent = message;
        status.className = `ai-extract-status small ${type}`;
    }

    function setField(name, value) {
        if (!value || !form.elements[name]) return false;
        form.elements[name].value = value;
        form.elements[name].dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }

    function renderPreview(fields, appliedCount) {
        if (!preview) return;
        const rows = Object.entries(labels)
            .filter(([key]) => fields[key])
            .map(([key, label]) => `<div><span>${label}</span><strong>${String(fields[key]).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}</strong></div>`)
            .join('');
        preview.hidden = false;
        preview.innerHTML = `
            <div class="ai-preview-header">
                <b>Applied ${appliedCount} fields</b>
                <span>Confidence: ${fields.confidence || 'unknown'}</span>
            </div>
            <div class="ai-preview-grid">${rows || '<p class="small">No fields were found.</p>'}</div>
        `;
    }

    async function extractAuctionFields() {
        const file = imageInput?.files?.[0] || null;
        const sourceUrl = (urlInput?.value || '').trim();
        if (!file && !sourceUrl) {
            setStatus('Upload an auction sheet image or paste a link first.', 'error');
            return;
        }

        const payload = new FormData();
        if (file) payload.append('auction_sheet_image', file);
        if (sourceUrl) payload.append('source_url', sourceUrl);

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Extracting...';
        setStatus('Reading auction details with AI. This can take up to a minute for photos.', 'loading');
        if (preview) preview.hidden = true;

        try {
            const response = await fetch('<?= app_url('actions/extract-import-assessment.php') ?>', {
                method: 'POST',
                body: payload
            });
            const raw = await response.text();
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (parseError) {
                const readableError = raw ? raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300) : 'AI extraction returned an unreadable response.';
                throw new Error(readableError);
            }
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'AI extraction failed.');
            }

            const fields = data.fields || {};
            if (fields.damage_notes && !fields.notes) {
                fields.notes = `Damage notes: ${fields.damage_notes}`;
            } else if (fields.damage_notes && fields.notes && !fields.notes.includes(fields.damage_notes)) {
                fields.notes = `${fields.notes}\nDamage notes: ${fields.damage_notes}`;
            }

            let appliedCount = 0;
            Object.entries(fields).forEach(([name, value]) => {
                if (setField(name, value)) appliedCount++;
            });
            renderPreview(fields, appliedCount);
            setStatus('Fields extracted. Review them before saving.', 'success');
        } catch (error) {
            setStatus(error.message || 'AI extraction failed.', 'error');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    button.addEventListener('click', extractAuctionFields);
    imageInput?.addEventListener('change', () => {
        if (imageInput.files?.[0]) {
            setStatus(`Selected ${imageInput.files[0].name}. Starting extraction...`);
            extractAuctionFields();
        }
    });
})();
</script>
<?php endif; ?>
<?php require '../footer.php'; ?>


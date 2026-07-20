<?php
require '../config/db.php';
require_once '../config/auth.php';
require_permission('can_view_imports');
require_once '../config/helpers.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$canManageImports = user_can('can_manage_imports');
$canViewFinance = user_can('can_view_import_finance');
$user = current_user();
$assessment = null;
$saveError = trim((string) ($_GET['save_error'] ?? ''));
$saved = isset($_GET['saved']);

if ($id) {
    require_import_assessment($pdo, $id);
    $stmt = $pdo->prepare('SELECT * FROM import_assessments WHERE id = ?');
    $stmt->execute([$id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$assessment && !$canManageImports) {
    http_response_code(403);
    die('You do not have permission to create import assessments.');
}

$settings = $pdo->query('SELECT setting_key, setting_value FROM import_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$users = (($user['role'] ?? '') === 'admin' || $canManageImports)
    ? $pdo->query("SELECT id, name FROM users WHERE role <> 'admin' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)
    : [];
$allowedUserIds = [];
if ($assessment) {
    $allowedStmt = $pdo->prepare('SELECT user_id FROM import_user_access WHERE assessment_id = ?');
    $allowedStmt->execute([(int) $assessment['id']]);
    $allowedUserIds = array_map('intval', $allowedStmt->fetchAll(PDO::FETCH_COLUMN));
}

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

$pageTitle = ($assessment ? 'Edit Import Assessment' : 'New Import Assessment') . ' | CarFlip HQ';
require '../header.php';
?>
<div class="container import-calculator-view">
    <div class="page-heading">
        <div>
            <div class="eyebrow">Japan Import Hub</div>
            <h1><?= $assessment ? htmlspecialchars((string) $assessment['import_ref']) : 'Auction & Landed Cost Calculator' ?></h1>
            <p class="small">Estimate FOB, Australian landed cost, expected profit, and the safest maximum hammer bid before auction.</p>
        </div>
        <a class="btn secondary" href="imports.php">Back to Imports</a>
    </div>

    <?php if (!$canViewFinance): ?>
        <div class="alert">Your account can use Japan import records, but landed-cost and profit fields are restricted.</div>
    <?php endif; ?>
    <?php if ($saveError !== ''): ?>
        <div class="alert error"><?= htmlspecialchars($saveError) ?></div>
    <?php elseif ($saved): ?>
        <div class="alert success">Import assessment saved.</div>
    <?php endif; ?>

    <form class="import-calculator" method="post" enctype="multipart/form-data" action="<?= app_url('actions/save-import-assessment.php') ?>">
        <?php if ($assessment): ?>
            <input type="hidden" name="id" value="<?= (int) $assessment['id'] ?>">
        <?php endif; ?>

        <?php if ($canViewFinance): ?>
        <div class="import-summary-grid" data-import-summary>
            <div class="stat-card"><span>Maximum Hammer</span><strong data-output="maxHammer">¥0</strong><small>Safe bid based on target profit</small></div>
            <div class="stat-card"><span>FOB</span><strong data-output="fobAud">$0.00</strong><small data-output="fobJpy">¥0</small></div>
            <div class="stat-card"><span>Total Pre-Sale Cost</span><strong data-output="totalCost">$0.00</strong><small>Before final sale</small></div>
            <div class="stat-card"><span>Expected Profit</span><strong data-output="profit">$0.00</strong><small data-output="margin">0.0% margin</small></div>
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

        <div class="import-layout">
            <section class="form-card import-section-card">
                <div class="section-kicker">Step 1</div>
                <h2>Vehicle & Auction</h2>
                <p class="small">Start with the car identity and auction reference. Access is controlled by the users you select below.</p>
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
                    <?php foreach (['Draft','Approved to Bid','Won','Shipped','Arrived','Compliance','Ready for Sale','Closed'] as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>" <?= import_value($assessment, $settings, 'status', 'Draft') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </section>

            <section class="form-card import-section-card">
                <div class="section-kicker">Step 2</div>
                <h2>Hammer to FOB</h2>
                <p class="small">Enter the auction bid and Japan-side costs. The live AUD/JPY rate is a suggestion and can be changed.</p>
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
            </section>

            <?php if ($canViewFinance): ?>
            <section class="form-card import-section-card finance-card">
                <div class="section-kicker">Step 3</div>
                <h2>Australia Landed Costs</h2>
                <p class="small">Adjust default freight, compliance, GST, duty, and selling assumptions to see the real landed position.</p>
                <div class="form-grid two">
                    <div><label>Expected Sale AUD</label><input data-calc type="number" step="0.01" min="0" name="expected_sale_price_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'expected_sale_price_aud')) ?>"></div>
                    <div><label>Target Profit AUD</label><input data-calc type="number" step="0.01" min="0" name="target_profit_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'target_profit_aud')) ?>"></div>
                    <div><label>Ocean Freight</label><input data-calc type="number" step="0.01" min="0" name="ocean_freight_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'ocean_freight_aud')) ?>"></div>
                    <div><label>Marine Insurance</label><input data-calc type="number" step="0.01" min="0" name="marine_insurance_aud" value="<?= htmlspecialchars(import_value($assessment, $settings, 'marine_insurance_aud')) ?>"></div>
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

        <?php if ($canManageImports): ?>
        <section class="form-card section-title import-section-card">
            <div class="section-kicker">Access</div>
            <h2>Sharing</h2>
            <p class="small">Select the database users who can see this import assessment. Admin accounts can always see every import.</p>
            <div class="sharing-toolbar">
                <span class="small">Users are loaded from the Users database.</span>
                <a class="btn secondary compact-btn" href="<?= app_url('pages/add-user.php') ?>">+ Add User</a>
            </div>
            <?php if ($users): ?>
                <div class="user-picker-grid">
                    <?php foreach ($users as $accessUser): ?>
                        <label class="user-picker-option">
                            <input type="checkbox" name="access_user_ids[]" value="<?= (int) $accessUser['id'] ?>" <?= in_array((int) $accessUser['id'], $allowedUserIds, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($accessUser['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">No users found yet. Add a user first, then return to share this import.</div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

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
        const japanFees = get('auction_fee_jpy') + get('japan_agent_fee_jpy') + get('inland_transport_jpy') + get('export_docs_jpy') + get('japan_port_fees_jpy') + get('other_japan_costs_jpy');
        const fobJpy = get('hammer_price_jpy') + japanFees;
        const fobAud = rate > 0 ? fobJpy / rate : 0;
        const freight = get('ocean_freight_aud');
        const insurance = get('marine_insurance_aud');
        const duty = get('duty_manual_aud') > 0 ? get('duty_manual_aud') : fobAud * get('duty_rate');
        const gstBase = fobAud + freight + insurance + duty;
        const gst = gstBase * get('gst_rate');
        const nonFobCosts = freight + insurance + get('port_charges_aud') + get('customs_broker_aud') + get('biosecurity_aud') + get('port_transport_aud') + get('compliance_aud') + get('registration_aud') + duty + gst + get('other_australia_costs_aud');
        const total = fobAud + nonFobCosts;
        const sale = get('expected_sale_price_aud');
        const profit = sale - total;
        const margin = sale > 0 ? (profit / sale) * 100 : 0;
        const maxTotal = sale - get('target_profit_aud');
        const maxFobAud = Math.max(0, maxTotal - nonFobCosts);
        const maxFobJpy = maxFobAud * rate;
        const maxHammer = Math.max(0, maxFobJpy - japanFees);
        const warnings = [];

        if (rate <= 0) warnings.push('Exchange rate is required.');
        if (profit < minimumProfit) warnings.push(`Expected profit is below ${money.format(minimumProfit)}.`);
        if (get('hammer_price_jpy') > maxHammer && maxHammer > 0) warnings.push('Hammer price is above the calculated maximum safe hammer.');

        set('maxHammer', yen.format(maxHammer));
        set('fobAud', money.format(fobAud));
        set('fobJpy', yen.format(fobJpy));
        set('totalCost', money.format(total));
        set('profit', money.format(profit));
        set('margin', `${margin.toFixed(1)}% margin`);
        setHtml('warnings', warnings.map(w => `<div class="alert warning">${w}</div>`).join(''));
        set('formula', [
            `Japan-side fees: ${yen.format(japanFees)}`,
            `FOB JPY: hammer + Japan fees = ${yen.format(fobJpy)}`,
            `FOB AUD: FOB JPY / exchange rate = ${money.format(fobAud)}`,
            `Duty estimate: ${money.format(duty)}`,
            `GST base: FOB AUD + freight + insurance + duty = ${money.format(gstBase)}`,
            `GST estimate: GST base x GST rate = ${money.format(gst)}`,
            `Total pre-sale cost: FOB AUD + Australian costs + duty + GST = ${money.format(total)}`,
            `Expected profit: expected sale - total pre-sale cost = ${money.format(profit)}`,
            `Maximum hammer: ((sale - target profit - non-FOB costs) x exchange rate) - Japan fees = ${yen.format(maxHammer)}`
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

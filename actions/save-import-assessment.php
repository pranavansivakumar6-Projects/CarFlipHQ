<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';
require_once '../config/import-status.php';

require_permission('can_manage_imports');

$user = current_user();
$id = post_int('id');
$canViewFinance = user_can('can_view_import_finance');

if ($id) {
    require_import_assessment($pdo, $id);
}

function import_money(string $key): float
{
    return post_money($key);
}

function import_generate_ref(PDO $pdo): string
{
    $prefix = 'JPN-' . date('Y');
    $stmt = $pdo->prepare('SELECT import_ref FROM import_assessments WHERE import_ref LIKE ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$prefix . '-%']);
    $latest = (string) $stmt->fetchColumn();
    $next = 1;

    if (preg_match('/-(\d+)$/', $latest, $matches)) {
        $next = (int) $matches[1] + 1;
    }

    return $prefix . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function import_calculation_snapshot(array $data): array
{
    $rate = (float) ($data['exchange_rate'] ?? 0);
    $shippingAud = (float) $data['ocean_freight_aud'] + (float) $data['marine_insurance_aud'];
    $japanFees = (float) $data['auction_fee_jpy']
        + (float) $data['japan_agent_fee_jpy']
        + (float) $data['inland_transport_jpy']
        + (float) $data['export_docs_jpy']
        + (float) $data['japan_port_fees_jpy']
        + (float) $data['other_japan_costs_jpy'];
    $fobJpy = (float) $data['hammer_price_jpy'] + $japanFees;
    $fobAud = $rate > 0 ? $fobJpy / $rate : 0;
    $duty = (float) $data['duty_manual_aud'] > 0 ? (float) $data['duty_manual_aud'] : $fobAud * (float) $data['duty_rate'];
    $cifAud = $fobAud + $shippingAud;
    $gstBase = $cifAud + $duty;
    $gst = $gstBase * (float) $data['gst_rate'];
    $melbourneCosts = (float) $data['port_charges_aud']
        + (float) $data['customs_broker_aud']
        + (float) $data['biosecurity_aud']
        + (float) $data['port_transport_aud']
        + (float) $data['compliance_aud']
        + (float) $data['registration_aud']
        + $duty
        + $gst
        + (float) $data['other_australia_costs_aud'];
    $total = $cifAud + $melbourneCosts;
    $sale = (float) $data['expected_sale_price_aud'];
    $profit = $sale - $total;

    return [
        'japan_side_fees_jpy' => round($japanFees, 2),
        'fob_jpy' => round($fobJpy, 2),
        'fob_aud' => round($fobAud, 2),
        'shipping_cif_addons_aud' => round($shippingAud, 2),
        'cif_before_melbourne_aud' => round($cifAud, 2),
        'duty_aud' => round($duty, 2),
        'gst_base_aud' => round($gstBase, 2),
        'gst_aud' => round($gst, 2),
        'melbourne_onroad_costs_aud' => round($melbourneCosts, 2),
        'total_landed_cost_aud' => round($total, 2),
        'total_pre_sale_cost_aud' => round($total, 2),
        'expected_profit_aud' => round($profit, 2),
        'profit_margin_percent' => $sale > 0 ? round(($profit / $sale) * 100, 2) : 0,
        'calculation_version' => 'jp-import-v2',
        'calculated_at' => date('c'),
    ];
}

$status = require_allowed_value(normalise_import_status(post_string('status') ?: 'Under Assessment'), import_status_steps(), 'status');
$baseData = [
    'make' => post_string('make', true),
    'model' => post_string('model', true),
    'variant' => post_string('variant'),
    'year' => post_int('year'),
    'chassis_vin' => post_string('chassis_vin'),
    'mileage' => post_int('mileage'),
    'auction_house' => post_string('auction_house'),
    'auction_date' => post_date_or_null('auction_date'),
    'auction_grade' => post_string('auction_grade'),
    'interior_grade' => post_string('interior_grade'),
    'lot_number' => post_string('lot_number'),
    'japan_agent' => post_string('japan_agent'),
    'status' => $status,
    'exchange_rate' => import_money('exchange_rate'),
    'hammer_price_jpy' => import_money('hammer_price_jpy'),
    'auction_fee_jpy' => import_money('auction_fee_jpy'),
    'japan_agent_fee_jpy' => import_money('japan_agent_fee_jpy'),
    'inland_transport_jpy' => import_money('inland_transport_jpy'),
    'export_docs_jpy' => import_money('export_docs_jpy'),
    'japan_port_fees_jpy' => import_money('japan_port_fees_jpy'),
    'other_japan_costs_jpy' => import_money('other_japan_costs_jpy'),
    'other_japan_costs_notes' => post_string('other_japan_costs_notes'),
    'notes' => post_string('notes'),
];

$financeFields = [
    'expected_sale_price_aud',
    'target_profit_aud',
    'ocean_freight_aud',
    'marine_insurance_aud',
    'port_charges_aud',
    'customs_broker_aud',
    'biosecurity_aud',
    'port_transport_aud',
    'compliance_aud',
    'registration_aud',
    'duty_rate',
    'duty_manual_aud',
    'gst_rate',
    'other_australia_costs_aud',
];

if ($canViewFinance) {
    foreach ($financeFields as $field) {
        $baseData[$field] = import_money($field);
    }
    $baseData['other_australia_costs_notes'] = post_string('other_australia_costs_notes');
} elseif ($id) {
    $stmt = $pdo->prepare('SELECT ' . implode(', ', array_merge($financeFields, ['other_australia_costs_notes'])) . ' FROM import_assessments WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($financeFields as $field) {
        $baseData[$field] = (float) ($existing[$field] ?? 0);
    }
    $baseData['other_australia_costs_notes'] = (string) ($existing['other_australia_costs_notes'] ?? '');
} else {
    $settings = $pdo->query('SELECT setting_key, setting_value FROM import_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($financeFields as $field) {
        $baseData[$field] = (float) ($settings[$field] ?? 0);
    }
    $baseData['other_australia_costs_notes'] = '';
}

$baseData['calculation_snapshot'] = json_encode(import_calculation_snapshot($baseData));
$baseData['calculation_version'] = 'jp-import-v2';
$baseData['updated_by'] = (int) $user['id'];

try {
    $previousStatus = null;
    if ($id) {
        $statusStmt = $pdo->prepare('SELECT status FROM import_assessments WHERE id = ?');
        $statusStmt->execute([$id]);
        $previousStatus = normalise_import_status((string) $statusStmt->fetchColumn());

        $columns = array_keys($baseData);
        $setSql = implode(', ', array_map(fn($column) => "$column = ?", $columns));
        $stmt = $pdo->prepare("UPDATE import_assessments SET $setSql WHERE id = ?");
        $stmt->execute(array_merge(array_values($baseData), [$id]));
        $assessmentId = $id;
        $action = 'updated';
    } else {
        $baseData['import_ref'] = import_generate_ref($pdo);
        $baseData['created_by'] = (int) $user['id'];
        $columns = array_keys($baseData);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $pdo->prepare('INSERT INTO import_assessments (' . implode(', ', $columns) . ") VALUES ($placeholders)");
        $stmt->execute(array_values($baseData));
        $assessmentId = (int) $pdo->lastInsertId();
        $action = 'created';
    }

    $audit = $pdo->prepare('INSERT INTO import_audit_log (assessment_id, user_id, action, details) VALUES (?, ?, ?, ?)');
    $audit->execute([$assessmentId, (int) $user['id'], $action, 'Import assessment ' . $action . '.']);
    if ($previousStatus !== null && $previousStatus !== $status) {
        $audit->execute([$assessmentId, (int) $user['id'], 'status_changed', 'Status changed from ' . $previousStatus . ' to ' . $status . '.']);
    }
} catch (PDOException $e) {
    error_log('Could not save import assessment: ' . $e->getMessage());
    $target = 'pages/import-calculator.php?save_error=' . urlencode('Could not create the import assessment. The database has been refreshed, so please try Create Assessment again.');
    if ($id) {
        $target = 'pages/import-calculator.php?id=' . $id . '&save_error=' . urlencode('Could not save the import assessment. Please try again.');
    }
    redirect_to($target);
}

redirect_to('pages/import-calculator.php?id=' . $assessmentId . '&saved=1');
?>

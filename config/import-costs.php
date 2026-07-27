<?php
function import_cost_definitions(): array
{
    return [
        'JP_PURCHASE' => ['Vehicle Purchase / Hammer Price', 'Japan Purchase', 'Japan Purchase', 'Variable', 'Included in FOB'],
        'JP_AUCTION_AGENT_EXPORT' => ['Auction Agent & Export Fees', 'Japan Purchase', 'Japan Purchase', 'Quoted bundle', 'Included in FOB'],
        'JP_INLAND_TRANSPORT' => ['Inland Transport - Japan', 'Japan Logistics', 'Japan Logistics', 'Variable', 'Included in FOB'],
        'JP_EXPORT_YARD_HANDLING' => ['Export Yard & Handling', 'Export', 'Japan Export', 'Schedule/quoted', 'Included in FOB'],
        'JP_STORAGE' => ['Japan Storage', 'Export', 'Japan Export', 'Conditional', 'Included in FOB when charged'],
        'JP_OTHER' => ['Other Japan Cost', 'Japan', 'Japan', 'Variable', 'Configurable'],
        'SHIP_OCEAN_FREIGHT' => ['Ocean Freight', 'Shipping/CIF', 'Shipping', 'Variable quote', 'Included in CIF'],
        'SHIP_EBS' => ['Emergency Bunker Surcharge', 'Shipping/CIF', 'Shipping', 'Conditional', 'Included in CIF when charged'],
        'SHIP_BAF' => ['Bunker Adjustment Factor', 'Shipping/CIF', 'Shipping', 'Conditional', 'Included in CIF when separate'],
        'SHIP_INSURANCE' => ['Marine Insurance', 'Shipping/CIF', 'Shipping', 'Quoted', 'Included in CIF'],
        'SHIP_HEAT_TREATMENT' => ['Heat Treatment', 'Shipping/CIF', 'Shipping', 'Conditional/seasonal', 'Included in CIF when charged'],
        'SHIP_OTHER' => ['Other Shipping Charge', 'Shipping/CIF', 'Shipping', 'Variable', 'Configurable'],
        'AU_CUSTOMS' => ['Customs / Broker', 'Melbourne/Australia', 'Australia', 'Pending', 'Configurable'],
        'AU_GOVERNMENT_TAXES' => ['Government Taxes', 'Melbourne/Australia', 'Australia', 'Calculated', 'Duty, GST, LCT if applicable'],
        'AU_PORT_TERMINAL' => ['Port / Terminal', 'Melbourne/Australia', 'Australia', 'Provider dependent', 'Avoid duplicate charging'],
        'AU_BIOSECURITY' => ['Biosecurity', 'Melbourne/Australia', 'Australia', 'Conditional', 'Inspection, cleaning, treatment'],
        'AU_LOCAL_TRANSPORT' => ['Local Transport', 'Melbourne/Australia', 'Australia', 'Quoted', 'Port to workshop or delivery'],
        'AU_COMPLIANCE' => ['Compliance', 'Melbourne/Australia', 'Australia', 'Quoted', 'Vehicle dependent'],
        'AU_ROADWORTHY_REGO' => ['Roadworthy / Registration', 'Final Landed Cost', 'Australia', 'Configurable', 'State and vehicle dependent'],
        'AU_PREP_REPAIRS' => ['Repairs / Preparation', 'Final Landed Cost', 'Australia', 'Actual', 'Mechanical, tyres, detailing'],
    ];
}

function import_cost_definition(string $code): array
{
    $definitions = import_cost_definitions();
    return $definitions[$code] ?? [$code, 'Other', 'Other', 'Variable', ''];
}

function import_cost_statuses(): array
{
    return ['Estimated', 'Pending Quote', 'Quoted', 'Confirmed', 'Paid', 'Not Applicable', 'Included Elsewhere', 'Disputed / Review'];
}

function import_cost_treatments(): array
{
    return ['Separate', 'Included Elsewhere', 'Not Applicable', 'Pending'];
}

function import_report_types(): array
{
    return ['Auction Sheet', 'FOB Budget', 'CIF Budget', 'Shipping Schedule', 'Invoice', 'Other'];
}

function import_save_report_upload(PDO $pdo, array $upload, int $assessmentId, int $userId): array
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose an Excel report first.');
    }
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Excel upload failed.');
    }
    if (($upload['size'] ?? 0) > 15 * 1024 * 1024) {
        throw new RuntimeException('Excel report must be 15MB or smaller.');
    }

    $original = (string) ($upload['name'] ?? 'report.xlsx');
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($extension, ['xlsx', 'xls'], true)) {
        throw new RuntimeException('Upload an .xlsx or .xls Japan report.');
    }

    $checksum = hash_file('sha256', $upload['tmp_name']);
    $duplicateStmt = $pdo->prepare('
        SELECT id
        FROM import_documents
        WHERE assessment_id = ? AND checksum_sha256 = ? AND archived_at IS NULL
        LIMIT 1
    ');
    $duplicateStmt->execute([$assessmentId, $checksum]);
    $duplicateId = (int) $duplicateStmt->fetchColumn();

    $uploadDir = dirname(__DIR__) . '/uploads/import-reports/' . $assessmentId;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Could not create the import report folder.');
    }

    $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $target = $uploadDir . '/' . $storedName;
    if (!move_uploaded_file($upload['tmp_name'], $target)) {
        throw new RuntimeException('Could not save the Excel report.');
    }

    $relativePath = 'uploads/import-reports/' . $assessmentId . '/' . $storedName;
    $parsed = import_parse_workbook($target, $extension);
    $reportType = import_detect_report_type($parsed['text']);
    $documentType = $reportType === 'FOB Budget' ? 'FOB Report' : ($reportType === 'CIF Budget' ? 'CIF Report' : 'Other');

    $versionStmt = $pdo->prepare('
        SELECT COALESCE(MAX(version_no), 0) + 1
        FROM import_documents
        WHERE assessment_id = ? AND document_type = ?
    ');
    $versionStmt->execute([$assessmentId, $documentType]);
    $version = (int) $versionStmt->fetchColumn();

    $pdo->prepare('UPDATE import_documents SET is_current = 0 WHERE assessment_id = ? AND document_type = ?')
        ->execute([$assessmentId, $documentType]);

    $documentStmt = $pdo->prepare('
        INSERT INTO import_documents
            (assessment_id, document_type, original_filename, stored_path, mime_type, file_size, checksum_sha256, version_no, is_current, sheet_names, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
    ');
    $documentStmt->execute([
        $assessmentId,
        $documentType,
        $original,
        $relativePath,
        (string) mime_content_type($target),
        (int) filesize($target),
        $checksum,
        $version,
        implode(', ', $parsed['sheet_names']),
        $userId,
    ]);
    $documentId = (int) $pdo->lastInsertId();

    $payload = import_build_parsed_payload($parsed, $reportType);
    $confidence = (float) ($payload['confidence'] ?? 0);
    $reportStmt = $pdo->prepare('
        INSERT INTO import_cost_reports
            (assessment_id, document_id, report_type, template_version, parser_confidence, approval_status, parsed_payload, imported_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $reportStmt->execute([
        $assessmentId,
        $documentId,
        $reportType,
        $payload['template_version'] ?? 'unknown',
        $confidence,
        'Needs Review',
        json_encode($payload),
        $userId,
    ]);
    $reportId = (int) $pdo->lastInsertId();

    return [
        'document_id' => $documentId,
        'report_id' => $reportId,
        'duplicate_document_id' => $duplicateId,
        'report_type' => $reportType,
    ];
}

function import_parse_workbook(string $path, string $extension): array
{
    if ($extension === 'xls') {
        return [
            'sheet_names' => ['Legacy XLS'],
            'rows' => [],
            'text' => '',
            'warnings' => ['Legacy .xls files are stored, but automatic parsing needs .xlsx. Save as .xlsx or map manually.'],
        ];
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Excel parser is not available on this server.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Excel workbook is unreadable or password protected.');
    }

    $sharedStrings = import_read_shared_strings($zip);
    $sheetNames = import_read_sheet_names($zip);
    $rows = [];
    $textParts = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
            continue;
        }

        $xml = simplexml_load_string((string) $zip->getFromName($name));
        if (!$xml) {
            continue;
        }

        foreach ($xml->sheetData->row as $row) {
            $rowValues = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $value = import_cell_value($cell, $sharedStrings);
                if ($value === '') {
                    continue;
                }
                $rowValues[$ref] = $value;
                $textParts[] = $value;
            }
            if ($rowValues) {
                $rows[] = $rowValues;
            }
        }
    }
    $zip->close();

    return [
        'sheet_names' => $sheetNames ?: ['Sheet1'],
        'rows' => $rows,
        'text' => implode(' ', $textParts),
        'warnings' => [],
    ];
}

function import_read_shared_strings(ZipArchive $zip): array
{
    $raw = $zip->getFromName('xl/sharedStrings.xml');
    if ($raw === false) {
        return [];
    }
    $xml = simplexml_load_string((string) $raw);
    if (!$xml) {
        return [];
    }

    $strings = [];
    foreach ($xml->si as $si) {
        $parts = [];
        if (isset($si->t)) {
            $parts[] = (string) $si->t;
        }
        foreach ($si->r as $run) {
            $parts[] = (string) $run->t;
        }
        $strings[] = trim(implode('', $parts));
    }

    return $strings;
}

function import_read_sheet_names(ZipArchive $zip): array
{
    $raw = $zip->getFromName('xl/workbook.xml');
    if ($raw === false) {
        return [];
    }
    $xml = simplexml_load_string((string) $raw);
    if (!$xml) {
        return [];
    }

    $names = [];
    foreach ($xml->sheets->sheet as $sheet) {
        $names[] = (string) $sheet['name'];
    }
    return $names;
}

function import_cell_value(SimpleXMLElement $cell, array $sharedStrings): string
{
    $type = (string) $cell['t'];
    if ($type === 's') {
        return trim((string) ($sharedStrings[(int) $cell->v] ?? ''));
    }
    if ($type === 'inlineStr') {
        return trim((string) ($cell->is->t ?? ''));
    }
    return trim((string) $cell->v);
}

function import_detect_report_type(string $text): string
{
    $normal = strtolower($text);
    if (str_contains($normal, 'cif budget') || (str_contains($normal, 'ocean freight') && str_contains($normal, 'marine insurance'))) {
        return 'CIF Budget';
    }
    if (str_contains($normal, 'estimated costs for vehicle export') || str_contains($normal, 'vehicle purchase price') || str_contains($normal, 'total estimated fob')) {
        return 'FOB Budget';
    }
    return 'Other';
}

function import_build_parsed_payload(array $parsed, string $reportType): array
{
    $rows = [];
    if ($reportType === 'FOB Budget') {
        $rows = [
            import_extract_cost_row($parsed['rows'], 'JP_PURCHASE', ['vehicle purchase', 'purchase price', 'winning bid'], 'AUD'),
            import_extract_cost_row($parsed['rows'], 'JP_AUCTION_AGENT_EXPORT', ['auction agent', 'export fees', 'auction-house'], 'AUD'),
            import_extract_cost_row($parsed['rows'], 'JP_INLAND_TRANSPORT', ['inland transport'], 'AUD'),
            import_extract_cost_row($parsed['rows'], 'JP_EXPORT_YARD_HANDLING', ['export yard', 'handling'], 'AUD'),
        ];
        $reported = import_extract_total($parsed['rows'], ['total estimated fob', 'estimated fob']);
    } elseif ($reportType === 'CIF Budget') {
        $rows = [
            import_extract_cost_row($parsed['rows'], 'SHIP_OCEAN_FREIGHT', ['ocean freight'], 'AUD'),
            import_extract_cost_row($parsed['rows'], 'SHIP_EBS', ['emergency bunker', 'ebs'], 'AUD', true),
            import_extract_cost_row($parsed['rows'], 'SHIP_INSURANCE', ['marine insurance', 'insurance'], 'AUD'),
            import_extract_cost_row($parsed['rows'], 'SHIP_HEAT_TREATMENT', ['heat treatment'], 'AUD', true),
        ];
        $reported = import_extract_total($parsed['rows'], ['total estimated cif', 'estimated cif']);
    } else {
        $reported = ['low' => 0, 'high' => 0, 'source_label' => ''];
    }

    $rows = array_values(array_filter($rows));
    $confidence = $reportType === 'Other' ? 0.15 : (count($rows) >= 3 ? 0.85 : 0.55);

    return [
        'report_type' => $reportType,
        'template_version' => strtolower(str_replace(' ', '-', $reportType)) . '-v1',
        'confidence' => $confidence,
        'sheet_names' => $parsed['sheet_names'],
        'warnings' => $parsed['warnings'],
        'items' => $rows,
        'reported_total' => $reported,
    ];
}

function import_extract_cost_row(array $rows, string $code, array $labelNeedles, string $currency, bool $conditional = false): ?array
{
    foreach ($rows as $row) {
        $label = implode(' ', array_values($row));
        $normal = strtolower($label);
        $matched = false;
        foreach ($labelNeedles as $needle) {
            if (str_contains($normal, strtolower($needle))) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            continue;
        }

        $numbers = import_money_numbers_from_row($row);
        $definition = import_cost_definition($code);
        return [
            'cost_code' => $code,
            'category' => $definition[1],
            'description' => $definition[0],
            'stage' => $definition[2],
            'low_estimate' => (float) ($numbers[0] ?? 0),
            'high_estimate' => (float) ($numbers[1] ?? ($numbers[0] ?? 0)),
            'actual_amount' => '',
            'currency' => $currency,
            'status' => 'Estimated',
            'treatment' => $conditional ? 'Pending' : 'Separate',
            'conditional_flag' => $conditional ? 1 : 0,
            'source_label' => trim($label),
            'source_cell' => (string) array_key_first($row),
            'notes' => $definition[4],
        ];
    }

    return null;
}

function import_extract_total(array $rows, array $labelNeedles): array
{
    foreach ($rows as $row) {
        $label = implode(' ', array_values($row));
        $normal = strtolower($label);
        foreach ($labelNeedles as $needle) {
            if (!str_contains($normal, strtolower($needle))) {
                continue;
            }
            $numbers = import_money_numbers_from_row($row);
            return [
                'low' => (float) ($numbers[0] ?? 0),
                'high' => (float) ($numbers[1] ?? ($numbers[0] ?? 0)),
                'source_label' => trim($label),
            ];
        }
    }

    return ['low' => 0, 'high' => 0, 'source_label' => ''];
}

function import_numbers_from_values(array $values): array
{
    $numbers = [];
    foreach ($values as $value) {
        preg_match_all('/-?\d+(?:,\d{3})*(?:\.\d+)?/', (string) $value, $matches);
        foreach ($matches[0] as $match) {
            $numbers[] = (float) str_replace(',', '', $match);
        }
    }
    return $numbers;
}

function import_money_numbers_from_row(array $row): array
{
    $numbers = [];

    foreach ($row as $value) {
        $text = trim((string) $value);
        if ($text === '') {
            continue;
        }

        $hasCurrencySignal = preg_match('/[$¥]|aud|jpy|yen|dollar/i', $text) === 1;
        $hasLetters = preg_match('/[a-zA-Z\p{Hiragana}\p{Katakana}\p{Han}]/u', $text) === 1;

        if ($hasLetters && $hasCurrencySignal) {
            preg_match_all('/(?:[$¥]|aud|jpy|yen|dollars?)\s*([0-9][0-9,]*(?:\.\d+)?)/i', $text, $prefixed);
            preg_match_all('/([0-9][0-9,]*(?:\.\d+)?)\s*(?:aud|jpy|yen|dollars?)/i', $text, $suffixed);
            foreach (array_merge($prefixed[1] ?? [], $suffixed[1] ?? []) as $match) {
                $numbers[] = (float) str_replace(',', '', $match);
            }
            continue;
        }

        if ($hasLetters && !$hasCurrencySignal) {
            continue;
        }

        preg_match_all('/\d+(?:,\d{3})*(?:\.\d+)?/', $text, $matches);
        foreach ($matches[0] as $match) {
            $numbers[] = (float) str_replace(',', '', $match);
        }
    }

    return $numbers;
}

function import_calculate_cost_summary(array $items): array
{
    $summary = [
        'fob_low' => 0.0,
        'fob_high' => 0.0,
        'shipping_low' => 0.0,
        'shipping_high' => 0.0,
        'cif_low' => 0.0,
        'cif_high' => 0.0,
        'actual_fob' => 0.0,
        'actual_shipping' => 0.0,
        'actual_cif' => 0.0,
    ];

    foreach ($items as $item) {
        $treatment = (string) ($item['treatment'] ?? 'Separate');
        $status = (string) ($item['status'] ?? 'Estimated');
        if (in_array($treatment, ['Included Elsewhere', 'Not Applicable'], true) || $status === 'Included Elsewhere' || $status === 'Not Applicable') {
            continue;
        }

        $code = (string) ($item['cost_code'] ?? '');
        $low = (float) ($item['low_estimate'] ?? 0);
        $high = (float) ($item['high_estimate'] ?? 0);
        $actual = $item['actual_amount'] === null || $item['actual_amount'] === '' ? null : (float) $item['actual_amount'];

        if (str_starts_with($code, 'JP_')) {
            $summary['fob_low'] += $low;
            $summary['fob_high'] += $high;
            if ($actual !== null) {
                $summary['actual_fob'] += $actual;
            }
        }
        if (str_starts_with($code, 'SHIP_')) {
            $summary['shipping_low'] += $low;
            $summary['shipping_high'] += $high;
            if ($actual !== null) {
                $summary['actual_shipping'] += $actual;
            }
        }
    }

    $summary['cif_low'] = $summary['fob_low'] + $summary['shipping_low'];
    $summary['cif_high'] = $summary['fob_high'] + $summary['shipping_high'];
    $summary['actual_cif'] = $summary['actual_fob'] + $summary['actual_shipping'];

    return array_map(fn ($value) => round($value, 2), $summary);
}
?>

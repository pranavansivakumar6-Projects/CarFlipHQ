<?php
return static function (PDO $pdo): void {
    $indexes = [
        ['cars', 'idx_cars_archived_created', ['archived_at', 'created_at']],
        ['cars', 'idx_cars_archived_status_created', ['archived_at', 'status', 'created_at']],
        ['tasks', 'idx_tasks_due_status_car', ['due_date', 'status', 'car_id']],
        ['expenses', 'idx_expenses_car_date_created', ['car_id', 'expense_date', 'created_at']],
        ['car_files', 'idx_car_files_car_created', ['car_id', 'created_at']],
        ['sale_listings', 'idx_sale_listings_car_date_created', ['car_id', 'listed_date', 'created_at']],
        ['car_purchase_payments', 'idx_purchase_payments_car_date_created', ['car_id', 'paid_date', 'created_at']],
        ['import_audit_log', 'idx_import_audit_assessment_created_id', ['assessment_id', 'created_at', 'id']],
        ['import_cost_items', 'idx_import_cost_items_assessment_code_id', ['assessment_id', 'cost_code', 'id']],
        ['import_cost_reports', 'idx_import_cost_reports_assessment_imported_id', ['assessment_id', 'imported_at', 'id']],
        ['import_documents', 'idx_import_documents_assessment_archived_created_id', ['assessment_id', 'archived_at', 'created_at', 'id']],
    ];

    $indexQuery = $pdo->prepare(
        'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX ' .
        'FROM INFORMATION_SCHEMA.STATISTICS ' .
        'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ' .
        'ORDER BY INDEX_NAME, SEQ_IN_INDEX'
    );

    foreach ($indexes as [$table, $indexName, $columns]) {
        $indexQuery->execute([$table]);
        $existingIndexes = [];
        foreach ($indexQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingIndexes[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
        }

        if (isset($existingIndexes[$indexName])) {
            if ($existingIndexes[$indexName] !== $columns) {
                throw new RuntimeException("Index {$indexName} already exists on {$table} with different columns.");
            }
            echo "INDEX SKIP {$table}.{$indexName}\n";
            continue;
        }

        $covered = false;
        foreach ($existingIndexes as $existingColumns) {
            if (array_slice($existingColumns, 0, count($columns)) === $columns) {
                $covered = true;
                break;
            }
        }
        if ($covered) {
            echo "INDEX COVERED {$table}.{$indexName}\n";
            continue;
        }

        $quotedColumns = array_map(
            static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`',
            $columns
        );
        $pdo->exec(
            'ALTER TABLE `' . str_replace('`', '``', $table) . '` ' .
            'ADD INDEX `' . str_replace('`', '``', $indexName) . '` (' . implode(', ', $quotedColumns) . ')'
        );
        echo "INDEX ADD {$table}.{$indexName}\n";
    }
};

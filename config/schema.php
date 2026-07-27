<?php
require_once __DIR__ . '/import-status.php';

function ensure_database_schema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $schemaChecked = true;

    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(100) NOT NULL,
          email VARCHAR(150) NOT NULL UNIQUE,
          password_hash VARCHAR(255) NOT NULL,
          role ENUM('admin','partner') DEFAULT 'partner',
          session_version INT NOT NULL DEFAULT 0,
          can_view_data TINYINT(1) NOT NULL DEFAULT 0,
          can_view_finance TINYINT(1) NOT NULL DEFAULT 0,
          can_manage_cars TINYINT(1) NOT NULL DEFAULT 0,
          can_manage_finance TINYINT(1) NOT NULL DEFAULT 0,
          can_manage_tasks TINYINT(1) NOT NULL DEFAULT 0,
          can_manage_sales TINYINT(1) NOT NULL DEFAULT 0,
          can_import_export TINYINT(1) NOT NULL DEFAULT 0,
          can_use_ai TINYINT(1) NOT NULL DEFAULT 0,
          can_view_imports TINYINT(1) NOT NULL DEFAULT 0,
          can_manage_imports TINYINT(1) NOT NULL DEFAULT 0,
          can_view_import_finance TINYINT(1) NOT NULL DEFAULT 0,
          access_requested_at DATETIME,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS password_resets (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          token_hash VARCHAR(255) NOT NULL,
          expires_at DATETIME NOT NULL,
          used_at DATETIME,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_password_resets_token (token_hash),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS investors (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(150) NOT NULL,
          email VARCHAR(150),
          phone VARCHAR(50),
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS sources (
          id INT AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(150) NOT NULL,
          website VARCHAR(255),
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS cars (
          id INT AUTO_INCREMENT PRIMARY KEY,
          make VARCHAR(100) NOT NULL,
          model VARCHAR(100) NOT NULL,
          year INT,
          color VARCHAR(50),
          body_type VARCHAR(80),
          vin VARCHAR(100),
          rego VARCHAR(50),
          odometer INT,
          source VARCHAR(100),
          purchase_price DECIMAL(10,2) DEFAULT 0,
          purchase_date DATE,
          status ENUM('Bought','Waiting for Parts','Under Repair','RWC Pending','Ready for Sale','Listed','Sold') DEFAULT 'Bought',
          estimated_sale_price DECIMAL(10,2) DEFAULT 0,
          actual_sale_price DECIMAL(10,2) DEFAULT 0,
          sold_date DATE,
          profile_photo VARCHAR(255),
          archived_at DATETIME,
          archived_by INT,
          damage_notes TEXT,
          notes TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS car_user_access (
          car_id INT NOT NULL,
          user_id INT NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (car_id, user_id),
          FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS expenses (
          id INT AUTO_INCREMENT PRIMARY KEY,
          car_id INT NOT NULL,
          category VARCHAR(100) NOT NULL,
          expense_name VARCHAR(150) NOT NULL,
          amount DECIMAL(10,2) NOT NULL,
          paid_by VARCHAR(100),
          expense_date DATE,
          receipt_file VARCHAR(255),
          notes TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS car_purchase_payments (
          id INT AUTO_INCREMENT PRIMARY KEY,
          car_id INT NOT NULL,
          paid_by VARCHAR(100) NOT NULL,
          amount DECIMAL(10,2) NOT NULL,
          paid_date DATE,
          notes TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS car_profit_shares (
          id INT AUTO_INCREMENT PRIMARY KEY,
          car_id INT NOT NULL,
          person_name VARCHAR(100) NOT NULL,
          share_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY unique_car_person (car_id, person_name),
          FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS car_files (
          id INT AUTO_INCREMENT PRIMARY KEY,
          car_id INT NOT NULL,
          file_type ENUM('photo','document') DEFAULT 'photo',
          title VARCHAR(150) NOT NULL,
          file_path VARCHAR(255) NOT NULL,
          notes TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS parts (
          id INT AUTO_INCREMENT PRIMARY KEY,
          car_id INT NOT NULL,
          part_name VARCHAR(150) NOT NULL,
          supplier VARCHAR(150),
          cost DECIMAL(10,2) DEFAULT 0,
          status ENUM('Needed','Ordered','Arrived','Installed','Cancelled') DEFAULT 'Needed',
          ordered_date DATE,
          arrived_date DATE,
          installed_date DATE,
          notes TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS sale_listings (
          id INT AUTO_INCREMENT PRIMARY KEY,
          car_id INT NOT NULL,
          platform VARCHAR(100),
          listing_price DECIMAL(10,2) DEFAULT 0,
          status ENUM('Draft','Listed','Offer Received','Deposit Taken','Sold','Withdrawn') DEFAULT 'Draft',
          listed_date DATE,
          buyer_name VARCHAR(150),
          buyer_contact VARCHAR(150),
          offer_amount DECIMAL(10,2) DEFAULT 0,
          deposit_amount DECIMAL(10,2) DEFAULT 0,
          notes TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS tasks (
          id INT AUTO_INCREMENT PRIMARY KEY,
          car_id INT NOT NULL,
          task_title VARCHAR(150) NOT NULL,
          description TEXT,
          assigned_to VARCHAR(100),
          priority ENUM('Low','Medium','High') DEFAULT 'Medium',
          status ENUM('To Do','In Progress','Done') DEFAULT 'To Do',
          hours_spent DECIMAL(8,2) DEFAULT 0,
          task_photo VARCHAR(255),
          due_date DATE,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS import_settings (
          id INT AUTO_INCREMENT PRIMARY KEY,
          setting_key VARCHAR(100) NOT NULL UNIQUE,
          setting_value DECIMAL(12,4) NOT NULL DEFAULT 0,
          notes VARCHAR(255),
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS import_assessments (
          id INT AUTO_INCREMENT PRIMARY KEY,
          import_ref VARCHAR(30) NOT NULL UNIQUE,
          make VARCHAR(100) NOT NULL,
          model VARCHAR(100) NOT NULL,
          variant VARCHAR(100),
          year INT,
          chassis_vin VARCHAR(120),
          mileage INT,
          auction_house VARCHAR(150),
          auction_date DATE,
          auction_grade VARCHAR(30),
          interior_grade VARCHAR(30),
          lot_number VARCHAR(80),
          japan_agent VARCHAR(120),
          status ENUM('Vehicle Found','Under Assessment','Approved to Bid','Auction Won','Import Approval Required','Import Approval Submitted','Import Approved','Shipping Booked','In Transit','Arrived at Port','Customs Clearance','Biosecurity','Transport to Workshop','Compliance','Roadworthy / Registration','Ready for Sale','Sold','Closed / Cancelled') DEFAULT 'Under Assessment',
          exchange_rate DECIMAL(12,4) DEFAULT 0,
          hammer_price_jpy DECIMAL(14,2) DEFAULT 0,
          auction_fee_jpy DECIMAL(14,2) DEFAULT 0,
          japan_agent_fee_jpy DECIMAL(14,2) DEFAULT 0,
          inland_transport_jpy DECIMAL(14,2) DEFAULT 0,
          export_docs_jpy DECIMAL(14,2) DEFAULT 0,
          japan_port_fees_jpy DECIMAL(14,2) DEFAULT 0,
          other_japan_costs_jpy DECIMAL(14,2) DEFAULT 0,
          other_japan_costs_notes VARCHAR(255),
          expected_sale_price_aud DECIMAL(12,2) DEFAULT 0,
          target_profit_aud DECIMAL(12,2) DEFAULT 0,
          ocean_freight_aud DECIMAL(12,2) DEFAULT 3000,
          marine_insurance_aud DECIMAL(12,2) DEFAULT 200,
          port_charges_aud DECIMAL(12,2) DEFAULT 800,
          customs_broker_aud DECIMAL(12,2) DEFAULT 350,
          biosecurity_aud DECIMAL(12,2) DEFAULT 300,
          port_transport_aud DECIMAL(12,2) DEFAULT 300,
          compliance_aud DECIMAL(12,2) DEFAULT 3000,
          registration_aud DECIMAL(12,2) DEFAULT 230,
          duty_rate DECIMAL(7,4) DEFAULT 0,
          duty_manual_aud DECIMAL(12,2) DEFAULT 0,
          gst_rate DECIMAL(7,4) DEFAULT 0.10,
          other_australia_costs_aud DECIMAL(12,2) DEFAULT 0,
          other_australia_costs_notes VARCHAR(255),
          calculation_snapshot LONGTEXT,
          calculation_version VARCHAR(30) DEFAULT 'jp-import-v1',
          notes TEXT,
          archived_at DATETIME,
          archived_by INT,
          created_by INT,
          updated_by INT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
          FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS import_user_access (
          assessment_id INT NOT NULL,
          user_id INT NOT NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (assessment_id, user_id),
          FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS import_audit_log (
          id INT AUTO_INCREMENT PRIMARY KEY,
          assessment_id INT NOT NULL,
          user_id INT,
          action VARCHAR(80) NOT NULL,
          details TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS import_documents (
          id INT AUTO_INCREMENT PRIMARY KEY,
          assessment_id INT NOT NULL,
          document_type ENUM('Auction Sheet','Purchase Invoice','FOB Report','CIF Report','Shipping Schedule','Export Certificate','Bill of Lading','Insurance','Customs','Biosecurity','Compliance','Registration','Other') DEFAULT 'Other',
          original_filename VARCHAR(255) NOT NULL,
          stored_path VARCHAR(255) NOT NULL,
          mime_type VARCHAR(120),
          file_size INT NOT NULL DEFAULT 0,
          checksum_sha256 CHAR(64) NOT NULL,
          version_no INT NOT NULL DEFAULT 1,
          is_current TINYINT(1) NOT NULL DEFAULT 1,
          sheet_names TEXT,
          uploaded_by INT,
          archived_at DATETIME,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_import_documents_assessment (assessment_id),
          INDEX idx_import_documents_checksum (checksum_sha256),
          FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
          FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS import_cost_reports (
          id INT AUTO_INCREMENT PRIMARY KEY,
          assessment_id INT NOT NULL,
          document_id INT NOT NULL,
          report_type ENUM('Auction Sheet','FOB Budget','CIF Budget','Shipping Schedule','Invoice','Other') DEFAULT 'Other',
          template_version VARCHAR(80),
          parser_confidence DECIMAL(5,2) DEFAULT 0,
          approval_status ENUM('Needs Review','Approved','Rejected') DEFAULT 'Needs Review',
          parsed_payload LONGTEXT,
          imported_by INT,
          imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          approved_by INT,
          approved_at DATETIME,
          INDEX idx_import_cost_reports_assessment (assessment_id),
          FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
          FOREIGN KEY (document_id) REFERENCES import_documents(id) ON DELETE CASCADE,
          FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL,
          FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS import_cost_items (
          id INT AUTO_INCREMENT PRIMARY KEY,
          assessment_id INT NOT NULL,
          report_id INT,
          document_id INT,
          cost_code VARCHAR(60) NOT NULL,
          category VARCHAR(80) NOT NULL,
          description VARCHAR(180) NOT NULL,
          stage VARCHAR(80) NOT NULL,
          low_estimate DECIMAL(14,2) DEFAULT 0,
          high_estimate DECIMAL(14,2) DEFAULT 0,
          actual_amount DECIMAL(14,2),
          currency VARCHAR(3) NOT NULL DEFAULT 'AUD',
          status ENUM('Estimated','Pending Quote','Quoted','Confirmed','Paid','Not Applicable','Included Elsewhere','Disputed / Review') DEFAULT 'Estimated',
          treatment ENUM('Separate','Included Elsewhere','Not Applicable','Pending') DEFAULT 'Separate',
          conditional_flag TINYINT(1) NOT NULL DEFAULT 0,
          source_label VARCHAR(180),
          source_cell VARCHAR(40),
          notes TEXT,
          created_by INT,
          updated_by INT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_import_cost_items_assessment (assessment_id),
          INDEX idx_import_cost_items_code (cost_code),
          FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
          FOREIGN KEY (report_id) REFERENCES import_cost_reports(id) ON DELETE SET NULL,
          FOREIGN KEY (document_id) REFERENCES import_documents(id) ON DELETE SET NULL,
          FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
          FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS import_cost_reconciliations (
          id INT AUTO_INCREMENT PRIMARY KEY,
          assessment_id INT NOT NULL,
          report_id INT,
          cost_scope VARCHAR(40) NOT NULL,
          reported_low DECIMAL(14,2) DEFAULT 0,
          reported_high DECIMAL(14,2) DEFAULT 0,
          calculated_low DECIMAL(14,2) DEFAULT 0,
          calculated_high DECIMAL(14,2) DEFAULT 0,
          variance_low DECIMAL(14,2) DEFAULT 0,
          variance_high DECIMAL(14,2) DEFAULT 0,
          severity ENUM('Info','Warning','Error') DEFAULT 'Info',
          resolution_status ENUM('Open','Accepted','Resolved') DEFAULT 'Open',
          resolution_note TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
          FOREIGN KEY (report_id) REFERENCES import_cost_reports(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS import_cost_history (
          id INT AUTO_INCREMENT PRIMARY KEY,
          assessment_id INT NOT NULL,
          cost_item_id INT,
          report_id INT,
          user_id INT,
          action VARCHAR(80) NOT NULL,
          before_value LONGTEXT,
          after_value LONGTEXT,
          reason TEXT,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (assessment_id) REFERENCES import_assessments(id) ON DELETE CASCADE,
          FOREIGN KEY (cost_item_id) REFERENCES import_cost_items(id) ON DELETE SET NULL,
          FOREIGN KEY (report_id) REFERENCES import_cost_reports(id) ON DELETE SET NULL,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    ensure_column($pdo, 'users', 'session_version', 'INT NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_view_data', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_view_finance', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_cars', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_finance', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_tasks', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_sales', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_import_export', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_use_ai', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_view_imports', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_imports', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_view_import_finance', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'access_requested_at', 'DATETIME');
    ensure_column($pdo, 'cars', 'profile_photo', 'VARCHAR(255)');
    ensure_column($pdo, 'cars', 'archived_at', 'DATETIME');
    ensure_column($pdo, 'cars', 'archived_by', 'INT');
    ensure_column($pdo, 'import_assessments', 'import_ref', 'VARCHAR(30)');
    ensure_column($pdo, 'import_assessments', 'make', 'VARCHAR(100)');
    ensure_column($pdo, 'import_assessments', 'model', 'VARCHAR(100)');
    ensure_column($pdo, 'import_assessments', 'variant', 'VARCHAR(100)');
    ensure_column($pdo, 'import_assessments', 'year', 'INT');
    ensure_column($pdo, 'import_assessments', 'chassis_vin', 'VARCHAR(120)');
    ensure_column($pdo, 'import_assessments', 'mileage', 'INT');
    ensure_column($pdo, 'import_assessments', 'auction_house', 'VARCHAR(150)');
    ensure_column($pdo, 'import_assessments', 'auction_date', 'DATE');
    ensure_column($pdo, 'import_assessments', 'auction_grade', 'VARCHAR(30)');
    ensure_column($pdo, 'import_assessments', 'interior_grade', 'VARCHAR(30)');
    ensure_column($pdo, 'import_assessments', 'lot_number', 'VARCHAR(80)');
    ensure_column($pdo, 'import_assessments', 'japan_agent', 'VARCHAR(120)');
    ensure_column($pdo, 'import_assessments', 'status', "ENUM('Vehicle Found','Under Assessment','Approved to Bid','Auction Won','Import Approval Required','Import Approval Submitted','Import Approved','Shipping Booked','In Transit','Arrived at Port','Customs Clearance','Biosecurity','Transport to Workshop','Compliance','Roadworthy / Registration','Ready for Sale','Sold','Closed / Cancelled') DEFAULT 'Under Assessment'");
    ensure_column($pdo, 'import_assessments', 'exchange_rate', 'DECIMAL(12,4) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'hammer_price_jpy', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'auction_fee_jpy', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'japan_agent_fee_jpy', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'inland_transport_jpy', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'export_docs_jpy', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'japan_port_fees_jpy', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'other_japan_costs_jpy', 'DECIMAL(14,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'other_japan_costs_notes', 'VARCHAR(255)');
    ensure_column($pdo, 'import_assessments', 'expected_sale_price_aud', 'DECIMAL(12,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'target_profit_aud', 'DECIMAL(12,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'ocean_freight_aud', 'DECIMAL(12,2) DEFAULT 3000');
    ensure_column($pdo, 'import_assessments', 'marine_insurance_aud', 'DECIMAL(12,2) DEFAULT 200');
    ensure_column($pdo, 'import_assessments', 'port_charges_aud', 'DECIMAL(12,2) DEFAULT 800');
    ensure_column($pdo, 'import_assessments', 'customs_broker_aud', 'DECIMAL(12,2) DEFAULT 350');
    ensure_column($pdo, 'import_assessments', 'biosecurity_aud', 'DECIMAL(12,2) DEFAULT 300');
    ensure_column($pdo, 'import_assessments', 'port_transport_aud', 'DECIMAL(12,2) DEFAULT 300');
    ensure_column($pdo, 'import_assessments', 'compliance_aud', 'DECIMAL(12,2) DEFAULT 3000');
    ensure_column($pdo, 'import_assessments', 'registration_aud', 'DECIMAL(12,2) DEFAULT 230');
    ensure_column($pdo, 'import_assessments', 'duty_rate', 'DECIMAL(7,4) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'duty_manual_aud', 'DECIMAL(12,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'gst_rate', 'DECIMAL(7,4) DEFAULT 0.10');
    ensure_column($pdo, 'import_assessments', 'other_australia_costs_aud', 'DECIMAL(12,2) DEFAULT 0');
    ensure_column($pdo, 'import_assessments', 'other_australia_costs_notes', 'VARCHAR(255)');
    ensure_column($pdo, 'import_assessments', 'calculation_snapshot', 'LONGTEXT');
    ensure_column($pdo, 'import_assessments', 'calculation_version', "VARCHAR(30) DEFAULT 'jp-import-v1'");
    ensure_column($pdo, 'import_assessments', 'notes', 'TEXT');
    ensure_column($pdo, 'import_assessments', 'archived_at', 'DATETIME');
    ensure_column($pdo, 'import_assessments', 'archived_by', 'INT');
    ensure_column($pdo, 'import_assessments', 'created_by', 'INT');
    ensure_column($pdo, 'import_assessments', 'updated_by', 'INT');
    ensure_column($pdo, 'import_documents', 'sheet_names', 'TEXT');
    ensure_column($pdo, 'import_cost_reports', 'parsed_payload', 'LONGTEXT');
    ensure_column($pdo, 'import_cost_items', 'treatment', "ENUM('Separate','Included Elsewhere','Not Applicable','Pending') DEFAULT 'Separate'");

    ensure_import_status_schema($pdo);
    seed_import_settings($pdo);
}

function ensure_import_status_schema(PDO $pdo): void
{
    $legacyAndCurrent = import_status_options_for_schema(true);
    $current = import_status_options_for_schema(false);
    $legacyMap = import_legacy_status_map();
    $targetType = strtolower(import_status_enum_sql($pdo, $current));

    $stmt = $pdo->prepare("
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'import_assessments'
          AND COLUMN_NAME = 'status'
        LIMIT 1
    ");
    $stmt->execute();
    if (strtolower((string) $stmt->fetchColumn()) === $targetType) {
        return;
    }

    $pdo->exec('ALTER TABLE import_assessments MODIFY status ' . import_status_enum_sql($pdo, $legacyAndCurrent) . " DEFAULT 'Under Assessment'");

    $stmt = $pdo->prepare('UPDATE import_assessments SET status = ? WHERE status = ?');
    foreach ($legacyMap as $legacy => $replacement) {
        $stmt->execute([$replacement, $legacy]);
    }

    $pdo->exec('ALTER TABLE import_assessments MODIFY status ' . import_status_enum_sql($pdo, $current) . " DEFAULT 'Under Assessment'");
}

function import_status_enum_sql(PDO $pdo, array $statuses): string
{
    $quotedStatuses = array_map(fn ($status) => $pdo->quote($status), $statuses);
    return 'ENUM(' . implode(',', $quotedStatuses) . ')';
}

function seed_import_settings(PDO $pdo): void
{
    $defaults = [
        ['ocean_freight_aud', 3000, 'Default ocean freight allowance'],
        ['marine_insurance_aud', 200, 'Default marine insurance allowance'],
        ['port_charges_aud', 800, 'Default Australian port and terminal allowance'],
        ['customs_broker_aud', 350, 'Default customs broker fee'],
        ['biosecurity_aud', 300, 'Default biosecurity cleaning allowance'],
        ['port_transport_aud', 300, 'Default port to workshop transport'],
        ['compliance_aud', 3000, 'Default compliance allowance'],
        ['registration_aud', 230, 'Default three-month registration allowance'],
        ['gst_rate', 0.10, 'GST estimate rate'],
        ['duty_rate', 0, 'Import duty estimate rate'],
        ['minimum_profit_aud', 2000, 'Warning threshold for expected profit'],
    ];

    $stmt = $pdo->prepare('INSERT IGNORE INTO import_settings (setting_key, setting_value, notes) VALUES (?, ?, ?)');
    foreach ($defaults as $default) {
        $stmt->execute($default);
    }
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}
?>

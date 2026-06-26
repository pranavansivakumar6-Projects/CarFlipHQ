<?php
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
          can_manage_cars TINYINT(1) NOT NULL DEFAULT 0,
          can_manage_finance TINYINT(1) NOT NULL DEFAULT 0,
          can_manage_tasks TINYINT(1) NOT NULL DEFAULT 0,
          can_manage_sales TINYINT(1) NOT NULL DEFAULT 0,
          can_import_export TINYINT(1) NOT NULL DEFAULT 0,
          can_use_ai TINYINT(1) NOT NULL DEFAULT 0,
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
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    ensure_column($pdo, 'users', 'session_version', 'INT NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_view_data', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_cars', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_finance', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_tasks', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_manage_sales', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_import_export', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'can_use_ai', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensure_column($pdo, 'cars', 'profile_photo', 'VARCHAR(255)');
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

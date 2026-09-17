<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require __DIR__ . '/config/db.php';

$migrationDirectory = __DIR__ . '/migrations';
$migrationFiles = glob($migrationDirectory . '/*.php') ?: [];
sort($migrationFiles, SORT_STRING);

try {
    $lockStatement = $pdo->query("SELECT GET_LOCK('carfliphq_schema_migrations', 10)");
    if ((int) $lockStatement->fetchColumn() !== 1) {
        throw new RuntimeException('Could not acquire the migration lock. Try again after the other migration process finishes.');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (' .
        'id INT AUTO_INCREMENT PRIMARY KEY, ' .
        'migration VARCHAR(190) NOT NULL UNIQUE, ' .
        'applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' .
        ')'
    );

    $applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $applied = array_fill_keys($applied, true);
    $recordMigration = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $appliedCount = 0;

    foreach ($migrationFiles as $migrationFile) {
        $migrationName = pathinfo($migrationFile, PATHINFO_FILENAME);
        if (isset($applied[$migrationName])) {
            echo "SKIP  {$migrationName}\n";
            continue;
        }

        $migration = require $migrationFile;
        if (!is_callable($migration)) {
            throw new RuntimeException("Migration {$migrationName} must return a callable.");
        }

        echo "RUN   {$migrationName}\n";
        $migration($pdo);
        $recordMigration->execute([$migrationName]);
        $appliedCount++;
        echo "DONE  {$migrationName}\n";
    }

    echo $appliedCount === 0
        ? "Database is already up to date.\n"
        : "Applied {$appliedCount} migration(s) successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    try {
        $pdo->query("SELECT RELEASE_LOCK('carfliphq_schema_migrations')");
    } catch (Throwable $ignored) {
        // The connection closing also releases the lock.
    }
}

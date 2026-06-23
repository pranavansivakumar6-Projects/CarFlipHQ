<?php
require '../config/db.php';
require '../config/auth.php';
require_admin();

function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;
    $escaped = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $buffer .= $char;

        if ($quote !== null) {
            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === $quote) {
                $quote = null;
            }

            continue;
        }

        if ($char === "'" || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

function should_skip_statement(string $statement): bool
{
    $normalized = preg_replace('/^\s*--.*$/m', '', $statement);
    $normalized = preg_replace('/^\s*\/\*![0-9]+\s+ALTER\s+TABLE.*(?:DISABLE|ENABLE)\s+KEYS\s+\*\/\s*;?\s*$/im', '', $normalized);
    $normalized = ltrim((string) $normalized);

    return $normalized === ''
        || preg_match('/^(CREATE\s+DATABASE|USE\s+|LOCK\s+TABLES|UNLOCK\s+TABLES)/i', $normalized) === 1;
}

if (empty($_FILES['backup_file']['tmp_name']) || !is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
    redirect_to('pages/restore-backup.php?error=missing');
}

$currentUserId = (int) (current_user()['id'] ?? 0);
$currentUser = null;
if ($currentUserId > 0) {
    $stmt = $pdo->prepare('SELECT name, email, password_hash, role FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$sql = file_get_contents($_FILES['backup_file']['tmp_name']);
if ($sql === false || trim($sql) === '') {
    redirect_to('pages/restore-backup.php?error=empty upload');
}

try {
    $pdo->beginTransaction();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    $statementNumber = 0;
    foreach (split_sql_statements($sql) as $statement) {
        $statementNumber++;
        if (should_skip_statement($statement)) {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            throw new RuntimeException('Statement ' . $statementNumber . ' failed: ' . $e->getMessage(), 0, $e);
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    if ($currentUser) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$currentUser['email']]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, role = ? WHERE id = ?');
            $stmt->execute([$currentUser['name'], $currentUser['password_hash'], 'admin', $existingId]);
            $_SESSION['user']['id'] = (int) $existingId;
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$currentUser['name'], $currentUser['email'], $currentUser['password_hash'], 'admin']);
            $_SESSION['user']['id'] = (int) $pdo->lastInsertId();
        }

        $_SESSION['user']['name'] = $currentUser['name'];
        $_SESSION['user']['email'] = $currentUser['email'];
        $_SESSION['user']['role'] = 'admin';
    }

    $pdo->commit();
    redirect_to('pages/restore-backup.php?restored=1');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = substr($e->getMessage(), 0, 240);
    error_log('Backup restore failed: ' . $message);
    redirect_to('pages/restore-backup.php?error=' . urlencode($message));
}
?>

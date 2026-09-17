<?php
return static function (PDO $pdo): void {
    require_once dirname(__DIR__) . '/config/schema.php';
    ensure_database_schema($pdo);
};

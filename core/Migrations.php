<?php
class Migrations
{
    public static function run(PDO $pdo)
    {
        if ((int) $pdo->query("SELECT GET_LOCK('tpt_schema_upgrade', 10)")->fetchColumn() !== 1) { throw new RuntimeException('Another migration is running.'); }
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(150) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
            foreach (glob(dirname(__DIR__) . '/database/migrations/*.php') as $file) {
                $version = basename($file);
                $check = $pdo->prepare('SELECT version FROM schema_migrations WHERE version = ?'); $check->execute(array($version));
                if ($check->fetchColumn()) { continue; }
                $migration = require $file;
                $migration($pdo); // MySQL DDL auto-commits; each operation must be rerunnable.
                $insert = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)'); $insert->execute(array($version));
                echo 'Applied ' . $version . PHP_EOL;
            }
        } finally { $pdo->query("SELECT RELEASE_LOCK('tpt_schema_upgrade')"); }
    }
    public static function column(PDO $pdo, $table, $column, $definition)
    {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $check->execute(array($table, $column));
        if (!$check->fetchColumn()) { $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition"); }
    }
}

<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — silent schema self-healing (admin only)
 * ---------------------------------------------------------------------------
 *  Migrations are normally applied from the command line
 *  (php bin/cli.php migrate). Installations created by importing database.sql
 *  never run them, which is why some dashboard screens (notification emails,
 *  chat transcripts, payment services) previously failed with SQL errors.
 *
 *  Schema::ensure() applies any pending migration files quietly, once per
 *  request, and never throws: if the database user cannot ALTER/CREATE, the
 *  page still renders and the failure is logged. Migrations are additive and
 *  re-runnable, so this is safe to call on every admin request.
 * --------------------------------------------------------------------------- */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}
require_once BASE_PATH . '/core/Migrations.php';

class Schema
{
    /** @var bool|null */
    private static $checked = null;

    /** @var string|null real reason when the upgrade could not run (for the UI) */
    public static $lastError = null;

    /**
     * Apply pending migrations silently. Call from admin screens that rely on
     * tables added after the original install.
     *
     * @return bool true when the schema is known to be up to date
     */
    public static function ensure()
    {
        if (self::$checked !== null) {
            return self::$checked;
        }
        self::$checked = false;

        if (!DB_OK || !isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            self::$lastError = 'The database is not connected.';
            return false;
        }

        /* Cheap check first: only take the migration lock when something is
           actually pending, so dashboard pages stay fast. */
        try {
            $applied = array();
            foreach (dbAll('SELECT version FROM schema_migrations') as $row) {
                $applied[(string) $row['version']] = true;
            }
            $pending = array();
            foreach (glob(dirname(__DIR__) . '/database/migrations/*.php') as $file) {
                $version = basename($file);
                if (!isset($applied[$version])) { $pending[] = $version; }
            }
            if (!$pending) {
                self::$checked = true;
                return true;
            }
        } catch (Throwable $probeError) {
            /* Fall through to the normal run() below (e.g. the tracking table
               does not exist yet). */
        }

        try {
            /* Migrations::run() prints progress — capture it so admin pages
               never emit stray text before their headers. */
            ob_start();
            Migrations::run($GLOBALS['pdo']);
            ob_end_clean();
            self::$checked = true;
        } catch (Throwable $schemaError) {
            if (ob_get_level() > 0) { ob_end_clean(); }
            self::$lastError = $schemaError->getMessage();
            error_log('[TPT] Schema upgrade skipped: ' . $schemaError->getMessage());
        }
        return self::$checked;
    }

    /** True when a table exists in the current database. */
    public static function hasTable($table)
    {
        if (!DB_OK) {
            return false;
        }
        $row = dbOne(
            'SELECT COUNT(*) AS c FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            array((string) $table)
        );
        return $row && (int) $row['c'] > 0;
    }

    /** True when a column exists on a table. */
    public static function hasColumn($table, $column)
    {
        if (!DB_OK) {
            return false;
        }
        $row = dbOne(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            array((string) $table, (string) $column)
        );
        return $row && (int) $row['c'] > 0;
    }
}

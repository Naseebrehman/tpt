<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — guarded fresh installer (CLI).
 * ---------------------------------------------------------------------------
 *  php bin/cli.php install
 *
 *  Rules:
 *   - EMPTY database            → full schema + starter content + admin.
 *   - PARTIAL (failed) install  → additive resume; nothing is overwritten.
 *   - ALREADY INSTALLED         → refuses and points to `php bin/cli.php migrate`.
 *   - UNKNOWN tables in the DB  → refuses; a foreign database is never touched.
 *
 *  Every SQL statement is executed individually (PDO::exec does NOT run
 *  multi-statement scripts) so errors name the exact statement, while the
 *  configured DB password is never included in any message.
 * ---------------------------------------------------------------------------
 */
class Installer
{
    /** Every table this application owns (lower-case). */
    public static $appTables = array(
        'admin_users', 'admin_lockouts', 'schema_migrations', 'contact_submissions',
        'blog_categories', 'blog_posts', 'blog_comments', 'portfolio', 'team_members',
        'testimonials', 'resources', 'newsletter_subscribers', 'chatbot_leads',
        'settings', 'notification_emails', 'email_templates',
        'page_views', 'payment_services', 'payment_records', 'payment_events',
    );

    /* =====================================================================
       SQL splitting: turn a dump into single statements safely.
       Handles -- / # line comments, block comments (keeping /*! ... * /
       executable comments), quoted strings, backtick identifiers and ;.
       ===================================================================== */
    public static function splitSql($sql)
    {
        $statements = array();
        $current    = '';
        $length     = strlen($sql);
        $i          = 0;
        while ($i < $length) {
            $char  = $sql[$i];
            $next  = $i + 1 < $length ? $sql[$i + 1] : '';
            /* line comments: "-- " (MySQL needs the space) or "#" */
            if (($char === '-' && $next === '-' && ($i + 2 >= $length || $sql[$i + 2] === ' ' || $sql[$i + 2] === "\t" || $sql[$i + 2] === "\n" || $sql[$i + 2] === "\r"))
                || $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") { $i++; }
                $current .= "\n";
                continue;
            }
            /* block comments; /*!12345 ... * / is executable and stays */
            if ($char === '/' && $next === '*') {
                $executable = $i + 2 < $length && $sql[$i + 2] === '!';
                $i += 2;
                $comment = '';
                while ($i < $length && !($sql[$i] === '*' && $i + 1 < $length && $sql[$i + 1] === '/')) { $comment .= $sql[$i]; $i++; }
                $i += 2;
                if ($executable) { $current .= '/*' . $comment . '*/'; }
                else { $current .= ' '; }
                continue;
            }
            /* quoted strings and identifiers */
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote   = $char;
                $current .= $char;
                $i++;
                while ($i < $length) {
                    $c = $sql[$i];
                    if ($c === '\\' && $quote !== '`' && $i + 1 < $length) {
                        $current .= $c . $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    $current .= $c;
                    $i++;
                    if ($c === $quote) {
                        /* doubled quote ('') continues the string */
                        if ($i < $length && $sql[$i] === $quote) { $current .= $quote; $i++; continue; }
                        break;
                    }
                }
                continue;
            }
            if ($char === ';') {
                if (trim($current) !== '') { $statements[] = trim($current); }
                $current = '';
                $i++;
                continue;
            }
            $current .= $char;
            $i++;
        }
        if (trim($current) !== '') { $statements[] = trim($current); }
        return $statements;
    }

    /* =====================================================================
       Error hygiene: classify driver errors, never leak the DB password.
       ===================================================================== */
    public static function sanitizeMessage($message)
    {
        $message = (string) $message;
        if (defined('DB_PASS') && DB_PASS !== '') {
            $message = str_replace(DB_PASS, '***', $message);
        }
        return $message;
    }

    /** Short human hint for a PDO driver error, from its code — no secrets. */
    public static function classifyError(PDOException $e)
    {
        $message   = self::sanitizeMessage($e->getMessage());
        $sqlstate  = $e->getCode();                 /* e.g. HY000, 42S02 */
        $code      = (int) $e->getCode();
        $errno     = 0;
        /* Connection-style:  SQLSTATE[HY000] [1045] Access denied ...       */
        if (preg_match('/\[\w+\]\s+\[(\d+)\]/', $message, $m)) {
            $errno = (int) $m[1];
        }
        /* Statement-style:   SQLSTATE[42S02]: Base table or view not found: 1146 ... */
        if (!$errno && preg_match('/:\s*(\d{4,5}):/', $message, $m2)) {
            $errno = (int) $m2[1];
        }
        $map = array(
            1044 => 'Access denied: this MySQL user lacks privileges for this database. Check DB_USER / grants in hPanel.',
            1045 => 'Access denied: wrong DB_USER or DB_PASS for this host. Check config/config.local.php.',
            1698 => 'Access denied: the server requires a different auth method for this user. Check DB_USER/DB_PASS.',
            1049 => 'Unknown database: DB_NAME does not exist. Create it in hPanel → Databases and copy the exact name.',
            2002 => 'Cannot reach the MySQL server. Check DB_HOST (usually "localhost" on Hostinger).',
            2003 => 'Cannot reach the MySQL server. Check DB_HOST (usually "localhost" on Hostinger).',
            2005 => 'Unknown MySQL server host. Check DB_HOST.',
            1064 => 'SQL syntax error in the schema/seed file.',
            1146 => "Missing table: the statement below expects a table that does not exist.",
            1054 => "Unknown column: a statement references a column that is not in the table.",
            1142 => 'Permission denied: the MySQL user cannot run this command (missing grant).',
            1043 => 'Permission denied by the server.',
            1062 => 'Duplicate entry: this row already exists (safe to re-run; existing data was kept).',
            1071 => 'Specified key was too long for the index (server index-length limit).',
            1215 => 'Cannot add foreign key constraint.',
            1216 => 'Foreign key constraint fails on insert.',
            1217 => 'Foreign key constraint fails on delete.',
            1451 => 'Cannot delete: rows referenced by another table (foreign key).',
            1452 => 'Cannot insert: referenced row missing (foreign key).',
            1205 => 'Lock wait timeout: another query holds the lock; retry shortly.',
            2006 => 'MySQL server has gone away (timeout/crash). Re-run the installer.',
            2013 => 'Lost connection to MySQL server during query. Re-run the installer.',
        );
        /* SQLSTATE fallbacks when the driver number was not present */
        $stateMap = array(
            '42S02' => "Missing table: the statement below expects a table that does not exist.",
            '42S22' => "Unknown column: a statement references a column that is not in the table.",
            '42000' => 'SQL syntax or permission problem — see the message and statement below.',
            '28000' => 'Access denied: check DB_USER and DB_PASS in config/config.local.php.',
            '23000' => 'Integrity constraint violated (duplicate or foreign key).',
            '08004' => 'The server refused the connection (quota or grants).',
            '08S01' => 'Connection to the MySQL server was lost. Re-run the installer.',
        );
        $hint = isset($map[$errno]) ? $map[$errno] : (isset($map[$code]) ? $map[$code] : '');
        if ($hint === '' && isset($stateMap[$sqlstate])) { $hint = $stateMap[$sqlstate]; }
        return array('message' => $message, 'errno' => $errno, 'hint' => $hint);
    }

    /** Execute ONE statement, re-throwing a detailed, password-free error. */
    public static function runStatement(PDO $pdo, $sql)
    {
        try {
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            $info = self::classifyError($e);
            $snippet = strlen($sql) > 160 ? substr($sql, 0, 160) . '…' : $sql;
            $text = 'SQL error ' . ($info['errno'] ? '(MySQL ' . $info['errno'] . ') ' : '') . '— ' . $info['message'];
            if ($info['hint'] !== '') { $text .= "\n      Hint: " . $info['hint']; }
            $text .= "\n      Statement: " . preg_replace('/\s+/', ' ', $snippet);
            throw new RuntimeException($text, 0, $e);
        }
    }

    /** Execute a whole .sql file statement-by-statement with progress. */
    public static function runSqlFile(PDO $pdo, $path)
    {
        if (!is_file($path)) {
            throw new RuntimeException('Required SQL file is missing: ' . basename($path));
        }
        $statements = self::splitSql((string) file_get_contents($path));
        foreach ($statements as $statement) {
            self::runStatement($pdo, $statement);
        }
        return count($statements);
    }

    /* =====================================================================
       Database state detection (empty / partial / installed / foreign).
       ===================================================================== */
    public static function analyze(PDO $pdo)
    {
        $existing = array();
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $row) {
            $existing[] = strtolower((string) $row[0]);
        }
        $app     = array_intersect($existing, self::$appTables);
        $unknown = array_diff($existing, self::$appTables);
        if (empty($existing)) {
            $status = 'empty';
        } elseif (!empty($unknown)) {
            $status = 'foreign';
        } elseif (in_array('admin_users', $app, true)) {
            $has = $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
            $status = ((int) $has > 0) ? 'installed' : 'partial';
        } else {
            $status = 'partial';
        }
        return array('status' => $status, 'existing' => $existing, 'app' => array_values($app), 'unknown' => array_values($unknown));
    }

    /* =====================================================================
       Install.
       ===================================================================== */
    public static function install(PDO $pdo, $email, $password)
    {
        $email = strtolower(trim((string) $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new RuntimeException('Use a valid email and password of at least 12 characters.');
        }

        /* [1] Database reachable ------------------------------------------------ */
        echo '[1/6] Connecting to the database...' . PHP_EOL;
        try {
            $server = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        } catch (PDOException $e) {
            $info = self::classifyError($e);
            throw new RuntimeException('Cannot use the database: ' . $info['message'], 0, $e);
        }
        echo '      Connected — server version ' . $server . PHP_EOL;

        /* [2] Detect current state --------------------------------------------- */
        echo '[2/6] Checking database state...' . PHP_EOL;
        $state = self::analyze($pdo);
        switch ($state['status']) {
            case 'installed':
                throw new RuntimeException('This database already has the application installed (admin_users is populated).'
                    . ' Nothing was changed. For schema upgrades use: php bin/cli.php migrate');
            case 'foreign':
                throw new RuntimeException('This database contains unknown tables (' . implode(', ', array_slice($state['unknown'], 0, 5))
                    . '). Refusing to modify a database this application does not own.'
                    . ' Create an empty database in hPanel → Databases and point DB_NAME at it.');
            case 'partial':
                echo '      Found an incomplete previous install (' . count($state['app']) . ' table(s) present, no admin account).'
                    . ' Resuming additively — existing rows are preserved.' . PHP_EOL;
                break;
            case 'empty':
                echo '      Database is empty — performing a fresh installation.' . PHP_EOL;
                break;
        }

        /* [3] Schema ------------------------------------------------------------ */
        echo '[3/6] Creating tables, indexes and relationships...' . PHP_EOL;
        $statementCount = self::runSqlFile($pdo, dirname(__DIR__) . '/database/schema-mysql.sql');
        echo '      Schema applied (' . $statementCount . ' statement(s)).' . PHP_EOL;

        /* [4] Starter content + default settings (idempotent) ------------------- */
        echo '[4/6] Seeding starter content and default settings...' . PHP_EOL;
        $seedSql = require dirname(__DIR__) . '/database/seed.php';
        if (!is_string($seedSql) || trim($seedSql) === '') {
            throw new RuntimeException('database/seed.php did not return seed SQL.');
        }
        foreach (self::splitSql($seedSql) as $statement) {
            self::runStatement($pdo, $statement);
        }
        echo '      Starter content and default settings in place.' . PHP_EOL;

        /* [5] Initial admin ------------------------------------------------------ */
        echo '[5/6] Creating the initial admin account...' . PHP_EOL;
        $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($adminCount > 0) {
            echo '      Admin account already exists — left untouched.' . PHP_EOL;
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($hash) || strlen($hash) < 50) {
                throw new RuntimeException('Could not generate a password hash on this PHP build (password_hash unavailable?).');
            }
            try {
                $st = $pdo->prepare('INSERT INTO admin_users (username, email, password_hash) VALUES (?, ?, ?)');
                $st->execute(array('admin', $email, $hash));
            } catch (PDOException $e) {
                $info = self::classifyError($e);
                throw new RuntimeException('Could not create the admin account: ' . $info['message'], 0, $e);
            }
            $check = $pdo->prepare('SELECT password_hash FROM admin_users WHERE email = ?');
            $check->execute(array($email));
            $stored = (string) $check->fetchColumn();
            if (!password_verify($password, $stored)) {
                throw new RuntimeException('Safety check failed: the created admin password does not verify. Installation aborted (no data was destroyed).');
            }
            echo '      Admin account created for ' . $email . ' (password stored as a bcrypt hash).' . PHP_EOL;
        }

        /* [6] Migrations ---------------------------------------------------------- */
        echo '[6/6] Recording schema version (migrations)...' . PHP_EOL;
        Migrations::run($pdo);

        /* [7] Installed marker (non-fatal) --------------------------------------- */
        $lockFile = dirname(__DIR__) . '/storage/runtime/installed.lock';
        if (@file_put_contents($lockFile, date(DATE_ATOM), LOCK_EX) === false) {
            echo '      Note: could not write storage/runtime/installed.lock — make storage/runtime writable by PHP.' . PHP_EOL;
        }
    }
}

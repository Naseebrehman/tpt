<?php
/**
 * ---------------------------------------------------------------------------
 *  DEVELOPMENT ONLY — MySQL data layer for the local WASM preview server.
 * ---------------------------------------------------------------------------
 *  The preview runtime runs PHP inside WebAssembly, where pdo_mysql cannot
 *  open a socket. This file provides the SAME dbAll()/dbOne()/dbExec()/
 *  dbInsert() contract as includes/db.php on top of mysqli, which does work,
 *  plus a PDO-shaped shim so migration code (`PDO $pdo`) can be exercised.
 *
 *  It is loaded by tests/harness/entry.php only when the preview server is
 *  started with TPT_HARNESS_DB=1. It is never loaded by the real site.
 */

if (defined('PIE_DEV_DB_LOADED')) {
    return;
}
define('PIE_DEV_DB_LOADED', true);

/* The application defines DB_OK inside includes/db.php; define it first so the
   site runs in "database available" mode, and swallow the redefinition notice. */
set_error_handler(function ($severity, $message) {
    if (stripos((string) $message, 'already defined') !== false) {
        return true;
    }
    return false;
}, E_WARNING | E_NOTICE | E_DEPRECATED);
if (!defined('DB_OK')) {
    define('DB_OK', true);
}

/** Shared mysqli connection for the preview server. */
function pieDevDb()
{
    static $link = null;
    if ($link instanceof mysqli) {
        return $link;
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
    $port = (int) (getenv('TPT_DB_PORT') ?: 3307);
    $name = defined('DB_NAME') ? DB_NAME : 'tpt_local';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    try {
        $link = @new mysqli($host, $user, $pass, $name, $port);
    } catch (Throwable $connectError) {
        error_log('[TPT-DEV] Connection failed: ' . $connectError->getMessage());
        $link = null;
        return null;
    }
    if ($link->connect_errno) {
        error_log('[TPT-DEV] Connection failed: ' . $link->connect_error);
        $link = null;
        return null;
    }
    $link->set_charset('utf8mb4');
    $link->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
    return $link;
}

/** Bind parameters with types inferred from the PHP values (PDO-like). */
function pieDevBind(mysqli_stmt $stmt, array $params)
{
    if (!$params) {
        return;
    }
    $types = '';
    $values = array();
    foreach ($params as $value) {
        if (is_int($value) || is_bool($value)) {
            $types .= 'i';
            $values[] = (int) $value;
        } elseif (is_float($value)) {
            $types .= 'd';
            $values[] = (float) $value;
        } elseif ($value === null) {
            $types .= 's';
            $values[] = null;
        } else {
            $types .= 's';
            $values[] = (string) $value;
        }
    }
    $stmt->bind_param($types, ...$values);
}

/** Prepare + execute + return the mysqli statement (or null on failure). */
function pieDevStatement($sql, array $params, $throw = false)
{
    $link = pieDevDb();
    if (!$link) {
        if ($throw) { throw new RuntimeException('Preview database is unavailable.'); }
        return null;
    }
    $stmt = @$link->prepare($sql);
    if (!$stmt) {
        error_log('[TPT-DEV] Prepare failed: ' . $link->error . ' | ' . $sql);
        if ($throw) { throw new RuntimeException($link->error . ' | ' . $sql); }
        return null;
    }
    if ($params) {
        pieDevBind($stmt, $params);
    }
    if (!@$stmt->execute()) {
        error_log('[TPT-DEV] Execute failed: ' . $stmt->error . ' | ' . $sql);
        if ($throw) { throw new RuntimeException($stmt->error . ' | ' . $sql); }
        return null;
    }
    return $stmt;
}

if (!function_exists('dbAll')) {
    function dbAll($sql, $params = array())
    {
        $stmt = pieDevStatement($sql, $params);
        if (!$stmt) {
            return array();
        }
        $rows = array();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('dbOne')) {
    function dbOne($sql, $params = array())
    {
        $rows = dbAll($sql, $params);
        return $rows ? $rows[0] : null;
    }
}

if (!function_exists('dbExec')) {
    function dbExec($sql, $params = array())
    {
        $stmt = pieDevStatement($sql, $params);
        if (!$stmt) {
            return -1;
        }
        return (int) $stmt->affected_rows;
    }
}

if (!function_exists('dbInsert')) {
    function dbInsert($sql, $params = array())
    {
        $link = pieDevDb();
        $stmt = pieDevStatement($sql, $params);
        if (!$stmt || !$link) {
            return -1;
        }
        return (int) $link->insert_id;
    }
}

/**
 * PDO-shaped shim so code type-hinted as `PDO` (Migrations, Installer,
 * SampleContent) can run against the same database during local testing.
 */
class PieDevPdoException extends PDOException
{
}

class PieDevPdo extends PDO
{
    public $link;

    public function __construct()
    {
        $this->link = pieDevDb();
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        PieDevPdoStatement::flushPending();
        $result = $this->link->query($statement);
        if ($result === false) {
            throw new PieDevPdoException('SQL error: ' . $this->link->error . ' | ' . $statement, (int) $this->link->errno);
        }
        return (int) $this->link->affected_rows;
    }

    #[\ReturnTypeWillChange]
    public function query($statement, ...$args)
    {
        PieDevPdoStatement::flushPending();
        $result = $this->link->query($statement);
        if ($result === false) {
            throw new PieDevPdoException('SQL error: ' . $this->link->error . ' | ' . $statement, (int) $this->link->errno);
        }
        if ($result === true) {
            return new PieDevPdoStatement($result, $this->link);
        }
        return new PieDevPdoStatement($result, $this->link);
    }

    #[\ReturnTypeWillChange]
    public function prepare($statement, $options = array())
    {
        PieDevPdoStatement::flushPending();
        $stmt = @$this->link->prepare($statement);
        if (!$stmt) {
            throw new PieDevPdoException('SQL error: ' . $this->link->error . ' | ' . $statement, (int) $this->link->errno);
        }
        return new PieDevPdoStatement($stmt, $this->link);
    }

    #[\ReturnTypeWillChange]
    public function lastInsertId($name = null)
    {
        return (string) $this->link->insert_id;
    }

    #[\ReturnTypeWillChange]
    public function setAttribute($attribute, $value)
    {
        return true;
    }
}

class PieDevPdoStatement
{
    private $stmt;
    private $link;
    private $rows = null;
    private $position = 0;
    /** Last statement created, so a pending result is consumed like PDO does. */
    private static $pending = null;

    public function __construct($stmt, $link)
    {
        $this->stmt = $stmt;
        $this->link = $link;
        if ($stmt instanceof mysqli_stmt || $stmt instanceof mysqli_result) {
            self::$pending = $this;
        }
    }

    /** Materialise a statement whose result set has not been read yet. */
    public static function flushPending()
    {
        if (self::$pending !== null) {
            $statement = self::$pending;
            self::$pending = null;
            try {
                $statement->materialize();
            } catch (Throwable $error) {
                error_log('[TPT-DEV] Could not buffer a pending result: ' . $error->getMessage());
            }
        }
    }

    #[\ReturnTypeWillChange]
    public function execute($params = array())
    {
        if ($this->stmt instanceof mysqli_stmt) {
            if ($params) {
                pieDevBind($this->stmt, array_values($params));
            }
            if (!$this->stmt->execute()) {
                throw new PieDevPdoException('SQL error: ' . $this->stmt->error, (int) $this->stmt->errno);
            }
            return true;
        }
        error_log('[TPT-DEV] execute() on a non-statement query.');
        return true;
    }

    public function materialize()
    {
        if ($this->rows !== null) {
            return;
        }
        if (self::$pending === $this) {
            self::$pending = null;
        }
        $this->rows = array();
        $result = null;
        try {
            if ($this->stmt instanceof mysqli_stmt) {
                $result = @$this->stmt->get_result();
            } elseif ($this->stmt instanceof mysqli_result) {
                $result = $this->stmt;
            }
        } catch (Throwable $error) {
            error_log('[TPT-DEV] Result set could not be read: ' . $error->getMessage());
            $result = null;
        }
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $this->rows[] = $row;
            }
        }
    }

    #[\ReturnTypeWillChange]
    public function fetchAll($mode = null)
    {
        $this->materialize();
        if ($mode === PDO::FETCH_NUM) {
            return array_map('array_values', $this->rows);
        }
        if ($mode === PDO::FETCH_COLUMN) {
            $out = array();
            foreach ($this->rows as $row) {
                $out[] = reset($row);
            }
            return $out;
        }
        return $this->rows;
    }

    #[\ReturnTypeWillChange]
    public function fetch($mode = null)
    {
        $this->materialize();
        if (!isset($this->rows[$this->position])) {
            return false;
        }
        $row = $this->rows[$this->position++];
        return $mode === PDO::FETCH_NUM ? array_values($row) : $row;
    }

    #[\ReturnTypeWillChange]
    public function fetchColumn($column = 0)
    {
        $this->materialize();
        if (!isset($this->rows[$this->position])) {
            return false;
        }
        $row = $this->rows[$this->position++];
        $values = array_values($row);
        return isset($values[$column]) ? $values[$column] : false;
    }

    #[\ReturnTypeWillChange]
    public function rowCount()
    {
        if ($this->stmt instanceof mysqli_stmt) {
            return (int) $this->stmt->affected_rows;
        }
        return 0;
    }
}

/* Schema::ensure() and admin sample-content seeding need $GLOBALS['pdo']. */
if (!defined('PIE_DEV_NO_AUTOPDO')) {
    $GLOBALS['pie_dev_pdo'] = new PieDevPdo();
}

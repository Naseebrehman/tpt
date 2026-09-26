<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — PDO connection
 * ---------------------------------------------------------------------------
 *  A single shared PDO instance in $GLOBALS['pdo'].
 *  DB_OK tells the rest of the app whether the database is reachable so the
 *  public site can degrade gracefully instead of dying with a white screen
 *  when the credentials in config.php have not been filled in yet.
 * ---------------------------------------------------------------------------
 */

require_once __DIR__ . '/config.php';

$GLOBALS['pdo'] = null;
$pieDbOk        = false;

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        )
    );
    $GLOBALS['pdo'] = $pdo;
    $pieDbOk        = true;
} catch (PDOException $e) {
    /* Never expose credentials or SQL errors to visitors — log only. */
    error_log('[TPT] Database connection failed: ' . $e->getMessage());
}

define('DB_OK', $pieDbOk);

/**
 * Small helper used everywhere: run a prepared statement and return rows.
 *
 * @param string $sql
 * @param array  $params
 * @return array
 */
function dbAll($sql, $params = array())
{
    if (!DB_OK) {
        return array();
    }
    try {
        $st = $GLOBALS['pdo']->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    } catch (PDOException $e) {
        error_log('[TPT] Query failed: ' . $e->getMessage() . ' | ' . $sql);
        return array();
    }
}

/**
 * Run a prepared statement and return the first row (or null).
 */
function dbOne($sql, $params = array())
{
    $rows = dbAll($sql, $params);
    return $rows ? $rows[0] : null;
}

/**
 * Run a prepared write statement, return affected row count (-1 on failure).
 */
function dbExec($sql, $params = array())
{
    if (!DB_OK) {
        return -1;
    }
    try {
        $st = $GLOBALS['pdo']->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    } catch (PDOException $e) {
        error_log('[TPT] Write failed: ' . $e->getMessage() . ' | ' . $sql);
        return -1;
    }
}

/**
 * Insert and return last insert id (-1 on failure).
 */
function dbInsert($sql, $params = array())
{
    if (!DB_OK) {
        return -1;
    }
    try {
        $st = $GLOBALS['pdo']->prepare($sql);
        $st->execute($params);
        return (int) $GLOBALS['pdo']->lastInsertId();
    } catch (PDOException $e) {
        error_log('[TPT] Insert failed: ' . $e->getMessage() . ' | ' . $sql);
        return -1;
    }
}

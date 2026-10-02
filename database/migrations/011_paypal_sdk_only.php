<?php
/**
 * Migration 011 is retained as a compatibility marker for installations that
 * already recorded its version. It intentionally performs no destructive
 * cleanup: payment credentials, transactions, templates and provider data
 * must never be dropped as part of an upgrade.
 *
 * Secure server-side gateway support is installed additively by migration 012.
 */
return function (PDO $pdo) {
    /* Deliberate no-op. Keep the filename/version so older migration ledgers
       remain compatible without repeating the former destructive changes. */
};

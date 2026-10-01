<?php
/** Migration 008 — company location.
 *
 *  Removes every Pakistan location from the stored settings. The public
 *  pages, JSON-LD, footer and emails all read `site_address` from this
 *  table, so an existing installation must be corrected here too — the
 *  code default alone would not change a live site.
 *  New value: Collingswood, New Jersey, USA (no street address invented).
 *
 *  Refreshes the stored Alia system prompt when it still contains an old
 *  location, so the assistant answers with the real company information.
 *
 *  Every statement is re-runnable (MySQL DDL auto-commits; the UPDATEs are
 *  idempotent because they only touch rows that still contain the old text). */
return function (PDO $pdo) {
    $newLocation = 'Collingswood, New Jersey, USA';

    /* 1 — settings rows that still name a Pakistan location. */
    $replacements = array(
        'Collingswood, NJ, USA · Punjab, Pakistan' => $newLocation,
        'Collingswood, NJ, USA and Punjab, Pakistan' => $newLocation,
        'Collingswood, NJ and Punjab, Pakistan' => $newLocation,
        'Punjab, Pakistan' => $newLocation,
        'Lahore, Pakistan' => $newLocation,
        'Punjab' => $newLocation,
    );
    $select = $pdo->prepare("SELECT setting_key, setting_value FROM settings
        WHERE setting_key IN ('site_address', 'chatbot_system_prompt')
          AND (setting_value LIKE '%Punjab%' OR setting_value LIKE '%Pakistan%' OR setting_value LIKE '%Lahore%')");
    $select->execute();
    /* Read the rows before preparing the next statement (buffered-safe). */
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);
    $update = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
    foreach ($rows as $row) {
        $value = str_replace(array_keys($replacements), array_values($replacements), (string) $row['setting_value']);
        /* Safety net: nothing may still name the old country. */
        $value = str_replace(array('Punjab', 'Pakistan', 'Lahore'), $newLocation, $value);
        $update->execute(array($value, $row['setting_key']));
    }

    /* A site address must never be left empty by the replacements above. */
    $address = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $address->execute(array('site_address'));
    $current = (string) $address->fetchColumn();
    if (trim($current) === '' || $current === 'Lahore, Pakistan') {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('site_address', '" . $newLocation . "')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    }
};

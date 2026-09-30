<?php
/** Admin-pasted payment implementations are complete self-contained frontend
 * documents (HTML + CSS + JavaScript) and must be stored exactly as provided.
 * TEXT caps a settings value at 64 KB, which could silently truncate a large
 * paste, so widen setting_value to MEDIUMTEXT (16 MB) when it is still TEXT.
 * Re-runnable: the current column type is checked before any ALTER. */
return function (PDO $pdo) {
    $check = $pdo->prepare(
        "SELECT DATA_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'setting_value'"
    );
    $check->execute();
    if ((string) $check->fetchColumn() === 'text') {
        $pdo->exec('ALTER TABLE `settings` MODIFY `setting_value` MEDIUMTEXT');
    }
};

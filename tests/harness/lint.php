<?php
/**
 * Development-only syntax check for every PHP file in the repository.
 *
 *   node tests/harness/cli.mjs tests/harness/lint.php
 */
$root = dirname(__DIR__, 2);
$skip = array('/.git/', '/node_modules/', '/vendor/');
$files = array();
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if (substr($path, -4) !== '.php') { continue; }
    foreach ($skip as $needle) { if (strpos($path, $needle) !== false) { continue 2; } }
    $files[] = $path;
}
sort($files);
$failures = 0;
foreach ($files as $file) {
    try {
        token_get_all(file_get_contents($file), TOKEN_PARSE);
    } catch (Throwable $error) {
        $failures++;
        echo 'FAIL ' . str_replace($root . '/', '', $file) . ' — ' . $error->getMessage() . PHP_EOL;
    }
}
echo PHP_EOL . count($files) . ' PHP file(s) checked, ' . $failures . ' syntax error(s).' . PHP_EOL;
exit($failures === 0 ? 0 : 1);

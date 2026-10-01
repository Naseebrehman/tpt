/**
 * Development-only helper: run a PHP script through the WASM runtime.
 *
 *   node tests/harness/cli.mjs tests/harness/db-setup.php
 *   node tests/harness/cli.mjs tests/harness/lint.php
 */
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { createPhp } from './php.mjs';

const BOOTSTRAP = `<?php
$tmp = getenv('TPT_HARNESS_TMP') ?: '/tmp/tpt-harness';
if (!is_dir($tmp . '/sessions')) { @mkdir($tmp . '/sessions', 0777, true); }
@ini_set('session.save_path', $tmp . '/sessions');
@ini_set('error_log', $tmp . '/php-error.log');
/* The WASM CLI SAPI defines neither STDOUT nor STDERR. */
if (!defined('STDOUT')) { define('STDOUT', fopen('php://stdout', 'wb')); }
if (!defined('STDERR')) { define('STDERR', fopen('php://stderr', 'wb')); }
$target = getenv('TPT_HARNESS_SCRIPT');
if ($target === false || $target === '' || !is_file($target)) { fwrite(STDERR, 'Harness bootstrap: script not found.'); exit(1); }
chdir(dirname($target));
require $target;
`;

const script = process.argv[2];
if (!script) {
	console.error('Usage: node tests/harness/cli.mjs <script.php> [args...]');
	process.exit(2);
}
/* Extra arguments are exposed as TPT_HARNESS_ARGS (comma-separated) so a test
   script can read them without a CLI SAPI. */
const scriptArgs = process.argv.slice(3);

const php = await createPhp();
/* Run through the bootstrap so sessions/logs stay in a writable temp dir;
   without it the WASM runtime's missing session directory breaks session_start(). */
const bootstrap = '/tmp/tpt-harness/cli-bootstrap.php';
if (!existsSync(bootstrap)) {
	mkdirSync('/tmp/tpt-harness/sessions', { recursive: true });
	writeFileSync(bootstrap, BOOTSTRAP);
}
const env = {
	TPT_HARNESS_DB: process.env.TPT_DB === '0' ? '0' : '1',
	TPT_HARNESS_SCRIPT: resolve(script),
	TPT_HARNESS_ARGS: scriptArgs.join(','),
	TPT_HARNESS_TMP: process.env.TPT_HARNESS_TMP || '/tmp/tpt-harness',
	TPT_DB_PORT: process.env.TPT_DB_PORT || '3307',
	TPT_ROOT: process.cwd(),
};
const response = await php.run({
	scriptPath: bootstrap,
	method: 'GET',
	headers: {},
	env,
	$_SERVER: {
		SCRIPT_FILENAME: script,
		SCRIPT_NAME: '/' + script,
		REQUEST_METHOD: 'GET',
		DOCUMENT_ROOT: process.cwd(),
		REMOTE_ADDR: '127.0.0.1',
	},
});
process.stdout.write(response.text || '');
if (response.errors) {
	process.stderr.write(String(response.errors) + '\n');
}
process.exit(response.exitCode === 0 ? 0 : 1);

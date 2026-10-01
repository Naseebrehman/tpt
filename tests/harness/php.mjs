/**
 * Development-only helper: loads a WebAssembly PHP runtime (WordPress
 * Playground's php-wasm) with the host filesystem mounted, so the site can be
 * rendered and previewed without a local Apache/PHP install.
 *
 * Requires (installed outside the repo, e.g. in /tmp):
 *   npm install @php-wasm/node @php-wasm/universal
 *
 * Nothing in this file ships to production: /tests is blocked by .htaccess.
 */
import { PHP, sandboxedSpawnHandlerFactory } from '@php-wasm/universal';
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';

let processCounter = 4000;

/** Boot one PHP runtime with the host filesystem mounted and cwd = repo root. */
export async function createPhp({ version = process.env.TPT_PHP_VERSION || '8.5' } = {}) {
	const runtime = await loadNodeRuntime(version, {
		emscriptenOptions: { processId: ++processCounter },
	});
	const php = new PHP(runtime);
	useHostFilesystem(php);
	php.chdir(process.cwd());
	try {
		await php.setSpawnHandler(sandboxedSpawnHandlerFactory());
	} catch (error) {
		/* Spawning is only needed for `php -l`; ignore if unavailable. */
	}
	return php;
}

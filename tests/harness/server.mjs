/**
 * ---------------------------------------------------------------------------
 *  DEVELOPMENT ONLY — local preview server for The Pie Technologies.
 * ---------------------------------------------------------------------------
 *  Serves the repository through a WebAssembly PHP runtime (php-wasm) using
 *  the same routing rules as the bundled .htaccess, so pages, forms, sessions
 *  and admin screens can be exercised locally (and by the Playwright tests in
 *  tests/*.cjs) without installing Apache, PHP or MySQL in the sandbox.
 *
 *  Usage (inside the sandbox):
 *    node tests/harness/server.mjs            # http://127.0.0.1:8123
 *    TPT_DB=0 node tests/harness/server.mjs   # run without the local database
 *
 *  Requires `npm install @php-wasm/node @php-wasm/universal` somewhere on
 *  NODE_PATH (see tests/harness/README.md). Never ships to production.
 */
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { createPhp } from './php.mjs';
import { PHPResponse } from '@php-wasm/universal';

const ROOT = process.env.TPT_ROOT || process.cwd();
const PORT = Number(process.env.TPT_PORT || 8123);
const HOST = process.env.TPT_HOST || '0.0.0.0';
const USE_DB = process.env.TPT_DB !== '0';

/** Paths that .htaccess refuses to serve (rewrite rule + FilesMatch). */
const BLOCKED = ['/includes', '/app', '/core', '/config', '/database', '/bin', '/tests', '/storage', '/views', '/docs', '/vendor'];

const MIME = {
	'.html': 'text/html; charset=utf-8', '.php': 'text/html; charset=utf-8',
	'.css': 'text/css; charset=utf-8', '.js': 'application/javascript; charset=utf-8',
	'.mjs': 'application/javascript; charset=utf-8', '.json': 'application/json; charset=utf-8',
	'.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
	'.webp': 'image/webp', '.gif': 'image/gif', '.ico': 'image/x-icon',
	'.pdf': 'application/pdf', '.txt': 'text/plain; charset=utf-8', '.xml': 'application/xml; charset=utf-8',
	'.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.mp4': 'video/mp4',
};

const php = await createPhp();
console.log('PHP runtime ready (' + (USE_DB ? 'local MySQL data layer on' : 'no data layer') + ')');

/** Map a URL path to the script Apache would execute (or null for static). */
function resolveTarget(pathname) {
	const local = path.join(ROOT, decodeURIComponent(pathname));
	if (!local.startsWith(ROOT)) {
		return { kind: 'forbidden' };
	}
	if (fs.existsSync(local)) {
		const stat = fs.statSync(local);
		if (stat.isFile()) {
			return pathname.endsWith('.php') ? { kind: 'php', file: local } : { kind: 'static', file: local };
		}
		if (stat.isDirectory() && fs.existsSync(path.join(local, 'index.php'))) {
			return { kind: 'php', file: path.join(local, 'index.php') };
		}
	}
	/* .htaccess: anything that is not a file or directory goes to front.php */
	return { kind: 'php', file: path.join(ROOT, 'front.php') };
}

function isBlocked(pathname) {
	if (/(^|\/)\.(?!well-known)/.test(pathname)) {
		return true;
	}
	return BLOCKED.some((prefix) => pathname === prefix || pathname.startsWith(prefix + '/'));
}

function sendStatic(res, file) {
	const type = MIME[path.extname(file).toLowerCase()] || 'application/octet-stream';
	const body = fs.readFileSync(file);
	res.writeHead(200, { 'Content-Type': type, 'Content-Length': body.length, 'Cache-Control': 'no-store' });
	res.end(body);
}

async function handlePhp(req, res, url, target) {
	const chunks = [];
	for await (const chunk of req) {
		chunks.push(chunk);
	}
	const body = Buffer.concat(chunks);
	const host = req.headers.host || `127.0.0.1:${PORT}`;
	const scriptName = '/' + path.relative(ROOT, target.file).split(path.sep).join('/');
	const serverVars = {
		DOCUMENT_ROOT: ROOT,
		SCRIPT_FILENAME: target.file,
		SCRIPT_NAME: scriptName,
		PHP_SELF: scriptName,
		REQUEST_URI: url.pathname + (url.search || ''),
		QUERY_STRING: url.search ? url.search.slice(1) : '',
		REQUEST_METHOD: req.method,
		SERVER_PROTOCOL: 'HTTP/1.1',
		SERVER_NAME: host.split(':')[0],
		SERVER_PORT: host.split(':')[1] || String(PORT),
		HTTP_HOST: host,
		REMOTE_ADDR: '127.0.0.1',
		HTTPS: 'off',
	};
	Object.entries(req.headers).forEach(([name, value]) => {
		if (name === 'content-length' || name === 'content-type') {
			return;
		}
		const key = 'HTTP_' + name.toUpperCase().replace(/-/g, '_');
		serverVars[key] = Array.isArray(value) ? value.join(', ') : String(value);
	});

	const response = await php.runStream({
		/* entry.php installs the optional data layer, then runs the real script. */
		scriptPath: path.join(ROOT, 'tests/harness/entry.php'),
		/* The query string must travel with the URI: php-wasm parses $_GET from
		   it, and every filtered/paginated admin screen depends on $_GET. */
		relativeUri: url.pathname + (url.search || ''),
		method: req.method,
		headers: req.headers,
		body: body.length ? body : undefined,
		env: {
			TPT_HARNESS_TARGET: target.file,
			TPT_HARNESS_ROOT: ROOT,
			TPT_HARNESS_DB: USE_DB ? '1' : '0',
			TPT_DB_PORT: process.env.TPT_DB_PORT || '3307',
			REQUEST_METHOD: req.method,
		},
		$_SERVER: serverVars,
	});
	const parsed = await PHPResponse.fromStreamedResponse(response);
	const headers = {};
	Object.entries(parsed.headers || {}).forEach(([name, values]) => {
		headers[name] = Array.isArray(values) ? values.join('\n') : String(values);
	});
	headers['Cache-Control'] = 'no-store';
	const text = new TextDecoder().decode(parsed.bytes);
	res.writeHead(parsed.httpStatusCode || 200, headers);
	res.end(text);
	if (parsed.errors) {
		console.error('[php] ' + String(parsed.errors).slice(0, 400));
	}
}

const server = http.createServer(async (req, res) => {
	const url = new URL(req.url, `http://${req.headers.host || '127.0.0.1'}`);
	const pathname = decodeURI(url.pathname);
	try {
		if (isBlocked(pathname)) {
			res.writeHead(403, { 'Content-Type': 'text/plain' });
			res.end('Forbidden');
			return;
		}
		/* .htaccess: /public/... redirects to the root; /admin → /admin/ */
		if (pathname === '/public' || pathname.startsWith('/public/')) {
			res.writeHead(308, { Location: pathname.replace(/^\/public/, '') || '/' });
			res.end();
			return;
		}
		if (pathname === '/admin') {
			res.writeHead(308, { Location: '/admin/' });
			res.end();
			return;
		}
		const target = resolveTarget(pathname);
		if (target.kind === 'forbidden') {
			res.writeHead(403);
			res.end('Forbidden');
			return;
		}
		if (target.kind === 'static') {
			sendStatic(res, target.file);
			return;
		}
		await handlePhp(req, res, url, target);
	} catch (error) {
		console.error('[harness] ' + (error && error.stack ? error.stack : error));
		if (!res.headersSent) {
			res.writeHead(500, { 'Content-Type': 'text/plain' });
		}
		res.end('Preview server error: ' + (error && error.message ? error.message : 'unknown'));
	}
});

server.listen(PORT, HOST, () => {
	console.log(`TPT preview server listening on http://${HOST}:${PORT} (root: ${ROOT})`);
});

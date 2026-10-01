# Local preview harness (development only)

These scripts let the site run **inside this sandbox** without Apache, PHP or
MySQL installed, so pages, forms, sessions and admin screens can be opened and
browser-tested locally. Nothing here ships to production — `/tests` is blocked
by `.htaccess`.

## What it is

| File | Purpose |
| --- | --- |
| `php.mjs` | Boots a WebAssembly PHP runtime (WordPress Playground `@php-wasm`) with the host filesystem mounted. |
| `server.mjs` | HTTP server that routes requests like the bundled `.htaccess` (static files served directly, everything else through `front.php`) and runs PHP through the WASM runtime. |
| `entry.php` | Per-request entry point: installs the optional local data layer, then runs the script Apache would have run. |
| `dev-db.php` | MySQL data layer for the preview (`mysqli`-backed `dbAll()/dbOne()/dbExec()/dbInsert()` plus a PDO-shaped shim) — the WASM runtime cannot open a PDO MySQL socket. |
| `db-setup.php` | Builds the preview database with the real installer (`core/Installer.php`). |
| `cli.mjs` | Runs any PHP file through the WASM runtime. |
| `lint.php` | Syntax-checks every PHP file in the repository. |

## Requirements (installed outside the repo)

```bash
# PHP runtime + browser driver (keep out of the repo — these are dev tools)
mkdir -p /tmp/phpwasm && cd /tmp/phpwasm
npm install @php-wasm/node @php-wasm/universal playwright-core
ln -s /tmp/phpwasm/node_modules /home/user/tpt/node_modules   # git-ignored

# MySQL server (npm package with prebuilt Linux binaries, run on port 3307)
mkdir -p /tmp/mysql && cd /tmp/mysql && npm pack mysql-server-5.7-lin-x64
tar xzf mysql-server-5.7-lin-x64-*.tgz
cd package/server && cp /tmp/mysql/package/server/libaio.so.1 .   # bundled by the 5.6 package
LD_LIBRARY_PATH=. ./mysqld --initialize-insecure --datadir=./data/mysql
```

`config/config.local.php` (git-ignored) points at that server:

```php
return array('APP_ENV' => 'development', 'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'tpt_local', 'DB_USER' => 'root', 'DB_PASS' => '',
    'SITE_URL' => 'http://127.0.0.1:8123', 'PRETTY_URLS' => true);
```

## Usage

```bash
# 1. MySQL on :3307 (see my.cnf in the extracted package)
# 2. Build/refresh the database
node tests/harness/cli.mjs tests/harness/db-setup.php

# 3. Start the preview server (http://127.0.0.1:8123)
TPT_PORT=8123 TPT_DB_PORT=3307 node tests/harness/server.mjs
TPT_DB=0 node tests/harness/server.mjs      # without a database (defaults only)

# 4. Syntax-check the whole codebase
node tests/harness/cli.mjs tests/harness/lint.php
```

## Browser tests

The Playwright tests in `tests/*.cjs` expect `playwright` and a Chromium path:

```bash
TPT_TEST_BASE_URL=http://127.0.0.1:8123 \
TPT_CHROMIUM_EXECUTABLE=/tmp/chromium/unpacked/chromium \
LD_LIBRARY_PATH=/tmp/chromium/unpacked/libs/lib \
node tests/responsive.cjs
```

External requests (Google Fonts, CDNs) are blocked in the sandbox, so those
tests route non-local URLs to `abort()`, which is why the pages are tested with
system fonts.

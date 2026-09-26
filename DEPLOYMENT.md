# Hostinger deployment and safe upgrades

## Deployment model

Keep this repository's **root** as the Apache document root (`public_html`).
Do not move the existing website into a new `public/` folder. Clean requests use
`.htaccess → front.php → core/bootstrap.php → app/routes.php → controllers`.
Original `.php` entry points, assets, templates, forms, and admin editors remain.
There is no Node, npm, Composer, Redis, worker or Docker production dependency.

Use a supported PHP 8.2+ release on Hostinger with PDO MySQL, mbstring, fileinfo,
cURL, JSON, sessions and OpenSSL. Source remains PHP 7.4-compatible in syntax,
but do not deploy an end-of-life PHP version. MySQL/MariaDB is required; SQLite
is deliberately not advertised as compatible with the existing application's SQL.
Apache needs mod_rewrite, AllowOverride and the existing Options permissions.

## Upgrade an existing site — no seeds

1. Make a private **database export and uploads backup**. Record the deployed
   revision and save the existing local config. Do not place backups in public_html.
2. Rehearse on a staging copy of the production database first. This checkout
   contained no production DB; existing production schema differences are unknown.
3. Deploy the code and hidden `.htaccess`. Preserve all existing uploads and
   `includes/config.local.php`. That configuration remains supported.
4. Optionally move deployment configuration to `config/config.local.php`, using
   `config/config.local.php.example`. The new location takes precedence over the
   legacy location. Both local files are ignored by Git and denied over HTTP.
5. Make `storage/runtime`, `storage/logs`, `storage/cache` and `uploads` writable
   by PHP. Prefer owner-only runtime access; never use world-writable permissions.
6. From Hostinger SSH/terminal with the correct PHP binary:
   ```sh
   php bin/cli.php migrate
   php tests/regression.php
   ```
   This adds columns to chatbot_leads/contact_submissions and creates
   payment_events/schema_migrations. It does **not** import or rewrite content,
   admin accounts, settings, transactions or lead records. Migration operations
   check existing columns, so interrupted DDL can be retried. MySQL DDL is not
   transactional. `GET_LOCK` prevents two migration runners from colliding.
7. Complete the staging acceptance checks below before publishing.

**Never re-import database.sql, database-upgrade.sql, schema-mysql.sql or seed.php
into an existing production database.** Legacy SQL files are retained for
compatibility/reference, not recommended as an upgrade mechanism.

Rollback: restore the previous code revision first; additive columns/tables can
remain. Restore the DB backup only during a coordinated maintenance window so
new submissions/payments are not lost. No automated destructive down migration
is provided.

## Fresh installation

1. Create an **empty** MySQL database in hPanel.
2. Copy `config/config.local.php.example` to `config/config.local.php`; enter the
   DB connection and canonical HTTPS SITE_URL. Keep APP_ENV production.
3. Run `php bin/cli.php install`. It prompts for the initial admin email and a
   password of at least 12 characters. It creates the original schema, preserves
   the original seed website content, then runs additive migrations. It does
   **not** install the legacy known-password admin account.
4. Installation refuses any database that already contains tables. An installation
   marker is also written under storage/runtime. There is intentionally no public
   web installer and no remotely reachable installer switch.
5. If installation fails partway through schema creation, inspect the error and
   use a new empty staging database before retrying; never delete a live database.

The installer uses configuration supplied beforehand rather than collecting DB
credentials in a public web form. A hosting operator with CLI access is required.

## Dashboard configuration

- **Settings → Branding:** PNG/JPEG/WebP logo and favicon, primary/secondary/accent
  colors, existing or system typography. Site name/contact/footer/OG image/socials
  retain their existing controls. Static SVG brand assets remain unchanged; new
  SVG uploads are rejected because active content can execute in browsers.
- **Content & SEO:** path-keyed title, description, canonical, OG image and noindex
  overrides; service headline/introduction/FAQ overrides reuse the original
  template. Blank fields retain existing content; restore clears only overrides.
- **Alia Leads:** search, status, notes, conversation snapshot, delete and CSV
  export (5,000-row cap). Existing website/contact leads remain in Submissions,
  including notification delivery status after migration.
- **Settings → Alia:** model, API key, prompt, temperature, maximum tokens, welcome,
  fallback, enabled state and optional lead collection. Choose a model available
  to your Gemini account. Model availability is not verified by offline tests.
- **SMTP:** save first, then send a test. Saved secrets are blank in admin HTML;
  leaving blank preserves them. From and Reply-To must be valid addresses.
  Configured SMTP failures no longer silently fall back to PHP mail. Success means
  the SMTP server accepted the message, not guaranteed inbox delivery; configure
  SPF/DKIM/DMARC and check spam folders. Admin sees the SMTP failure stage/response.
- **Payments:** existing Stripe/PayPal/USD workflows remain. Add Stripe signing
  secret and register `https://YOUR-DOMAIN/api/webhooks/stripe` for
  `checkout.session.completed` and `checkout.session.async_payment_succeeded`.
  The signature, timestamp, reference, session ID, USD currency and amount must
  match before a transaction is marked paid. Event IDs are deduplicated in a DB
  transaction. Configure test/live settings consistently.

## API compatibility

GET `/api/search?q=...` returns published resources/articles and matching services.
POST `/api/contact`, `/api/lead`, `/api/newsletter`, `/api/payment` accept existing
form fields or a flat JSON object with `csrf_token`. Obtain the token from the
rendered session-bound form. `/api/lead` shares contact validation and stores in
contact_submissions (minimum message length 10). These are same-site APIs, not
public cross-origin integrations. Payment JSON returns a `redirect` URL for the
hosted checkout/status page; no browser secret is returned.

POST `/api/chat` accepts the current Alia JSON payload plus `csrf_token`; it uses
an authenticated session token for lead ownership, not client-provided session_id.
Legacy chatbot-api.php still uses the same handler. Optional phone/company/service
fields are accepted by the server; the preserved widget asks for name/email.

Rate limiting uses a locked, bounded file in storage/runtime. Unauthenticated
mutations are limited to 60 per IP per 10 minutes, chat additionally to 20. A
missing/unwritable runtime directory fails closed. Forwarding headers are not
trusted: on a reverse proxy, configure trusted client-IP restoration in Apache
rather than letting arbitrary X-Forwarded-For bypass limits.

## Acceptance checks still required on Hostinger staging

- `/`, all legacy `.php` pages, `/about`, `/services`, every service, `/work`,
  resource/article/project details, `/admin/`, `/admin/login`, asset/PDF URLs.
- `/public/about` redirects once to `/about`; no loops or HTTP 500. Check HTTPS
  proxy behavior, admin relative links, clean paths and query strings.
- `config/config.local.php`, `includes/config`, `storage/runtime/ratelimits.json`,
  `.git/HEAD`, `database/schema-mysql.sql` must be HTTP 403 (never source/download).
- Compare desktop/mobile screenshots, content, animation hooks and menus with
  the previous deployment. Static CSS and original assets were not replaced.
- Login/lockout/logout/password change, all old editor CRUD/upload/export flows,
  new leads/settings/content screens and migrations run twice without data loss.
- Contact/newsletter/chat/payment success, invalid input, invalid CSRF, throttling,
  database outage, SMTP accepted/rejected credentials, no secrets in public HTML.
- Stripe sandbox completion/cancellation, signed/forged/stale/duplicate webhook,
  wrong amount/currency, webhook retry after DB outage; PayPal sandbox approval,
  capture and repeated status-page loads. Never mark paid from return query flags.
- Generated canonical/OG/Twitter/JSON-LD/sitemap and noindex overrides.

## Verified here and remaining scope

`tests/regression.php`: 66 passing route/security/helper/PHP syntax checks under
PHP 8.5 WebAssembly (development tooling outside the repository). JavaScript
syntax check passes. Sixteen public template smoke renders and five admin template renders passed
using an isolated fixture that simulated DB-unavailable mode and a fixture admin
identity. Sentinel SMTP/Gemini secrets were not exposed in the rendered HTML. An unmodified bootstrap
cannot be integration-tested in that runtime: its MySQL connection attempt traps
inside the WebAssembly runtime. Native PHP/Apache/MySQL tests remain mandatory.
No actual SMTP delivery, Gemini call, payment charge, migration or production
installation was performed.

This is an **incremental architecture upgrade**, not completion of every item in
the larger specification. Remaining work includes a full arbitrary-page/legal
body and navigation editor, deeper service-section editing, unified contact/Alia
lead list, invoice entities and non-USD currencies, PayPal webhooks, password-reset
emails/workflow, payment success/failure email templates, CAPTCHA integration,
robots editing, and extraction of remaining legacy page SQL/handlers. Existing
payment requests are not mislabeled as a new invoicing system, and empty duplicate
schema tables were not added merely to match requested filenames.

## Responsive follow-up

See [docs/RESPONSIVE-UPDATE.md](docs/RESPONSIVE-UPDATE.md) for the mobile menu,
System/accordion fixes, lighter surfaces, Alia portrait, portfolio directory-route
fix, sample-content controls and additional browser tests.

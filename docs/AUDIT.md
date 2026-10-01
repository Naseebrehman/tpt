# Existing application audit — 2026-09-26

Baseline: 50de4a0. Working tree was clean. No production database, secrets, or
live integration credentials are available in this checkout. This is a source
review, not a claim that external integrations have been tested.

1. **Folders:** root PHP public entry points, `services/`, `portfolio/`,
   `admin/`, shared `includes/`, static `assets/`, seeded `uploads/resources/`.
   Source inventory accompanies this report. Keep root as Hostinger web root.
2. **Routing:** Apache extensionless rewrites, service directory exception,
   resource/blog/work/portfolio slugs and tokenized payment links. No PHP router.
3. **Database:** MySQL PDO, prepared helpers with graceful offline fallbacks.
   Tables: admin_users, admin_lockouts, contact_submissions, blog_categories,
   blog_posts, blog_comments, portfolio, team_members, testimonials, resources,
   newsletter_subscribers, chatbot_leads, payments, settings, page_views.
   `settings` already centralizes SMTP, AI, payment and branding settings; do
   not create parallel tables for the same settings. `portfolio` is projects,
   `payments` is the existing transaction/request ledger. SQL seed imports are
   not a safe repeatable migration mechanism. Never reimport on a live database.
4. **Admin:** authenticated dashboard, submission filters/status/notes/export,
   payments, blog/editor/comments, portfolio/editor, resources, team,
   testimonials, subscribers, settings and password change. Preserve all.
5. **Forms:** contact POST/AJAX in contact.php, newsletter endpoint, blog
   comments, resource downloads, payment requests; CSRF largely present.
   Controllers and SQL are often in the page entry point before the template.
6. **Email:** reusable sendEmail, branded templates, optional PHPMailer and
   built-in SMTP, dashboard test. Silent PHP mail fallback can falsely imply
   SMTP success; Reply-To and useful admin diagnostics missing.
7. **Chat:** already named Alia in UI; older README says PIE Bot. Gemini key
   is server-side, model hard-coded to gemini-1.5-flash. Optional name/email
   capture writes chatbot_leads, without lead-management screen, CSRF or limits.
8. **Payments:** existing PayPal and Stripe hosted checkouts, USD records and
   Admin Services/Payments screens. PHP validates each request and verifies the
   provider before recording success; optional Stripe webhooks are signature-
   verified and re-fetched. Do not trust browser callbacks or return flags.
9. **Auth:** password_verify/hash, login session rotation, DB lockouts, CSRF;
   imported seed has a documented default account. New installation must prompt
   for a strong password, not create another known credential.
10. **SEO:** shared title/description/canonical/OG/Twitter and optional JSON-LD,
    dynamic sitemap, robots.txt, article meta fields. Canonical uses request URI
    including tracking parameters. Keep existing metadata and published paths.
11. **APIs:** chatbot-api.php wrapper, newsletter.php, admin/actions.php,
    contact.php AJAX; no uniform /api routes.
12. **Assets:** existing CSS design/sections/admin, vanilla main/admin/chat JS,
    local brand/photos/icons, PDFs, CDN animation/chart/editor libraries.
    No frontend rebuild necessary; keep assets and animation hooks unchanged.
13. **Config:** includes/config.php, ignored includes/config.local.php,
    TPT_* env values, DB settings cache. Add config/ location with legacy fallback.
14. **Hostinger:** PHP 7.4+ style source, MySQL, Apache mod_rewrite and AllowOverride,
    OpenSSL/cURL/mbstring/fileinfo/PDO MySQL, writable uploads and runtime.
    No runtime Node, Composer, queue, Redis or Docker requirement.

## Safety and implementation approach

Keep the original pages/templates and schema names. Add routing adapters before
incrementally extracting handlers. Introduce only additive, tracked migrations;
back up DB and uploads externally before running them. Never run seed SQL against
an existing installation. Protect all newly introduced non-public directories.
Maintain legacy .php URLs. No /public URL prefix is needed for this root layout.

Security priorities: distrust client-supplied forwarding IP headers, protect chat,
reject active SVG uploads, confine upload/delete paths, mask saved secrets in
admin HTML, avoid SMTP false success and add provider webhook verification.

## Scope tracking

See DEPLOYMENT.md and final delivery notes for verified behavior and remaining
work. A compatibility controller is intentionally transitional; not every legacy
view is claimed to be free of SQL. SQLite is not a supported production database:
existing queries use MySQL enums, date functions and upsert syntax.

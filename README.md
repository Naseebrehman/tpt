# The Pie Technologies — Full-Service Growth Agency Website

A PHP + MySQL website for **The Pie Technologies**: 13 public pages,
7 service pages, a Gemini-powered chatbot, and a full admin dashboard (submissions, blog,
resources, portfolio, team, testimonials, subscribers, settings).

Dark editorial design system (violet/cyan), Trifid-style oversized headings, pinned scroll
sections, custom cursor, magnetic buttons, particles hero, marquee, count-up stats,
Swiper testimonials, Chart.js graphs, AOS reveals — all with graceful CDN fallbacks.

---

## Architecture upgrade (September 2026)

**Existing installations: read [DEPLOYMENT.md](DEPLOYMENT.md) before deploying.**
Run `php bin/cli.php migrate` against a backed-up staging copy first. Do not
reimport the legacy SQL seeds into production. New installs can use
`php bin/cli.php install`, which creates a user-chosen strong admin password.

The existing frontend, assets and dashboard remain. Clean URLs now have a
compatibility router (`front.php`, `app/routes.php`); contact handling has a
controller/repository boundary; working SMTP, Gemini and uploads live in `core/`
with legacy include wrappers. Payment services remain admin-managed; the complete custom gateway code is pasted by the site owner in Admin → Payments. Three values reach that code without the owner editing it — the PayPal Client ID, the single shared Terms & Conditions URL and the service list — and PayPal orders are created with `shipping_preference: 'NO_SHIPPING'` because payments are for digital services. Branding, Alia controls/leads, content/SEO
overrides and dependency-free regression tests were added. Gateway processing is intentionally left to owner-managed frontend code.
See [the audit](docs/AUDIT.md) and deployment guide for limitations and pending
staging checks. This is an incremental upgrade, not a claim that every legacy
page has been rewritten into MVC or every requested CMS feature is complete.

## 1. Installation and Hostinger Git deployment

This repository is already arranged as the **web document root**: `index.php`, `.htaccess`,
`assets/`, `includes/`, `admin/`, and the other public PHP pages are at the repository root.
There should not be another `public_html/` folder inside the repository. When connecting it in
Hostinger hPanel → **Git**, set the deployment directory to the domain's document root:
`public_html` (or `domains/your-domain.com/public_html` for an addon domain). Deploy the
repository root contents there, not into `public_html/public_html`. The `.git` metadata is
blocked from web requests by the included `.htaccess`.

1. In hPanel, connect this GitHub repository and select the branch you want to publish. Set
   its destination to the correct domain document root as described above, then deploy.
2. Confirm `index.php` and `.htaccess` are directly inside that document root. If your domain
   uses a different document root, use that folder instead.
3. **Create a MySQL database** in hPanel → Databases. Note the database name, user, password,
   and host shown by Hostinger.
4. For a fresh installation, run `php bin/cli.php install` over SSH — see the
   "Fresh installation" section of DEPLOYMENT.md. It applies the schema
   statement-by-statement, seeds starter content, and prompts for your own
   admin email/password. For an existing installation, run
   `php bin/cli.php migrate` only; never reimport seed SQL into production.
5. Copy `config/config.local.php.example` to `config/config.local.php` and enter
   your DB credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) and real
   domain. This is the only file that should hold live credentials. It is
   ignored by Git, so future Git deployments will not overwrite it, and the
   bundled `.htaccess` denies web access to it. `DB_HOST` is often `localhost`,
   but use the host shown in hPanel. The legacy `includes/config.local.php`
   location is still read if the new file does not exist. Alternatively,
   configure the documented `TPT_*` environment variables.
6. **Folder permissions:** `/uploads/` → `755` (and its subfolders); PHP files → `644`.
   Ensure `/uploads/` is writable by PHP so admin uploads work.
7. Visit your domain, then `your-domain.com/admin/login.php`.
8. **Login:** use the email and password chosen during CLI installation. If the site
   was previously installed using the legacy SQL seed, rotate that old default password immediately.
9. Add SMTP credentials in *Settings → SMTP* and click **Send Test Email**.
10. Add your Gemini API key in *Settings → Gemini API* and click **Test Connection**.
11. Replace placeholder images in `/assets/images/` with real photography and update agency
    information in *Settings → Site Settings*.

> The site degrades gracefully if the database is not yet configured: public pages still render,
> but database-backed content and admin features will not be available. Make sure the live-only
> `includes/config.local.php` exists before launch.

---

## 2. What's inside

### Public pages
| Path | Purpose |
|---|---|
| `/index.php` | Home: hero + typed headline, particles, marquee, stats, work preview, services accordion, pinned "THE SYSTEM" scroll section, founder CTA, testimonials, why-us, blog preview, final CTA |
| `/services/meta-ads.php` … `/services/branding-design.php` | 7 service pages (no pricing anywhere on Meta Ads) |
| `/portfolio.php` | Filterable case-study grid (JS filter, no reload) |
| `/portfolio/case-study.php?slug=…` | Case study detail (clean URL `/portfolio/<slug>`) with Chart.js results graph |
| `/resources.php` | Guides + templates (tracked downloads), blog extract, video library, newsletter signup |
| `/blog.php`, `/blog-single.php` | Category filter, search, pagination (9/page); mobile-first article page with share, related posts and a newsletter subscription section |
| `/about.php` | Story, mission/vision/values, team from DB, stats |
| `/contact.php` | Validated AJAX form → DB + admin email + branded client auto-reply + animated success state |
| `/sitemap.php`, `/robots.txt`, `/404.php`, `/maintenance.php`, `/privacy-policy.php`, `/terms.php` | Utility pages |

### Admin (`/admin/`)
`login.php` (bcrypt + 5-attempt/15-min lockout + CSRF), `index.php` (stat cards, 30-day line
chart, service bar chart, recent table, quick links, system status), `submissions.php`
(filters, bulk actions, CSV export, detail modal with notes + status), `blog.php` +
`blog-edit.php` (TinyMCE, uploads, SEO fields, auto slug + reading time), `resources.php`,
`portfolio.php` + `portfolio-edit.php` (Sortable.js reorder, stats/chart JSON editors),
`team.php` (drag-and-drop order), `testimonials.php`, `subscribers.php`,
`chats.php` (Alia conversations: full transcript, status, edit, delete),
`payments.php` (paste the complete custom PayPal/Stripe payment implementation — HTML/CSS/JS — with enable/disable toggle, Save/Edit/Clear controls; rendered verbatim on Pay Online, with the admin's PayPal Client ID, the shared Terms & Conditions URL and the shared Services list injected through `{{PLACEHOLDERS}}`),
`settings.php` (SMTP / AI providers / notifications / email templates / Site / Social /
Maintenance tabs with live test buttons),
`password.php`, `actions.php` (AJAX router), `export.php` (CSV).

### Alia
Widget on every page (footer). Client: `assets/js/chatbot.js` (history in `sessionStorage`,
skippable name/email lead capture). Backend: `includes/chatbot-api.php` routed through the
public `chatbot-api.php` endpoint — reads the key + editable system prompt from the
`settings` table, calls the admin-selected Gemini model, stores leads in `chatbot_leads`, and falls back
to a friendly offline message if the API is unreachable or unconfigured.

---

## 3. Configuration reference

Set deployment-specific values in the untracked `/includes/config.local.php` file (start from
`config.local.php.example`) or provide the matching `TPT_*` environment variables. `config.php`
contains safe defaults and computes the paths automatically.

| Constant | Meaning |
|---|---|
| `APP_ENV` | `production` hides PHP errors (default). Use `development` while debugging. |
| `DB_*` | MySQL host, database name, username, and password. |
| `SITE_URL` | Canonical domain for SEO/OG/sitemap. |
| `PRETTY_URLS` | `true` = extension-less links (needs the bundled `.htaccess`). Set `false` on hosts without mod_rewrite. |
| `BASE_URL` | Auto-detected, so sub-folder installs work too. |

Everything else (SMTP, Gemini, agency info, socials, analytics, maintenance mode, founder
name, default SEO tags) lives in the **settings table**, editable from the dashboard.

---

## 4. Email

`sendEmail()` tries, in order: **PHPMailer** (if you run `composer require phpmailer/phpmailer`
in the site root so `/vendor/autoload.php` exists), then the **built-in SMTP client**
(`core/Mailer.php`, supports TLS/SSL + AUTH LOGIN), and reports delivery failure without silently falling back to PHP `mail()`.
Templates: branded dark admin notification + client auto-reply + newsletter welcome
(`includes/email-templates.php`).

---

## 5. Security checklist (implemented)

- CSRF token on every form (session-based, `hash_equals` verification)
- PDO prepared statements only — zero string-concatenated SQL
- `htmlspecialchars()` on all output, `sanitize()`/`filter_input`-style validation on intake
- Uploads: extension + real MIME (finfo) + 5 MB cap + `uniqid()` rename, stored outside code paths
- Admin: `requireAdmin()` at the top of every `/admin/` file, session regenerated on login,
  fully destroyed on logout, 5-failure/15-minute lockouts in `admin_lockouts`
- `.htaccess`: HTTPS force, `/includes/*.php` forbidden, upload PHP execution blocked,
  `.sql/.log/.md` denied, security headers, gzip + caching
- Errors logged, never displayed in production; SQL errors never reach the browser
- Honeypot field on the contact form; chatbot input length-capped and history-sanitised

---

## 6. Performance & quality notes

- System fonts fallback + `display=swap` Google Fonts; images lazy-loaded with width/height set
- CDN libs (AOS, Swiper, Chart.js, Typed.js, particles.js, Sortable, TinyMCE) are loaded with
  `defer` **and** each has a vanilla-JS fallback in `main.js`, so a blocked CDN never blanks content
- Videos are click-to-load (no third-party iframes until the visitor asks)
- All animations are transform/opacity only (60 fps), with `prefers-reduced-motion` respected
- Keyboard-navigable: skip link, focus-visible rings, ARIA labels on every widget
- No horizontal scroll from 320 px to 2560 px (`overflow-x: clip` + fluid `clamp()` layout)

---

## 7. Routine maintenance

- **Blog:** Admin → Blog → New Post (TinyMCE). Slug, reading time and excerpt auto-generate.
- **Case studies:** Admin → Portfolio. Stats are `VALUE | LABEL` per line; chart is Chart.js JSON.
- **Guides:** Admin → Resources (upload cover + PDF). Download counts track automatically.
- **Backups:** export the MySQL database weekly (phpMyAdmin → Export) and keep the `/uploads/` folder.
- **Replace the seeded content** (testimonials, portfolio, team, blog posts) with real client data
  before launch, and regenerate team portraits at `/assets/images/team-1..3.jpg`.

Built with plain PHP 7.4+ / MySQL 5.7+ — no framework, no build step, no licence fees.

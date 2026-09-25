# The Pie Technologies — Full-Service Growth Agency Website

A complete, production-ready PHP + MySQL website for **The Pie Technologies**: 13 public pages,
7 service pages, a Gemini-powered chatbot, and a full admin dashboard (submissions, blog,
resources, portfolio, team, testimonials, subscribers, settings).

Dark editorial design system (violet/cyan), Trifid-style oversized headings, pinned scroll
sections, custom cursor, magnetic buttons, particles hero, marquee, count-up stats,
Swiper testimonials, Chart.js graphs, AOS reveals — all with graceful CDN fallbacks.

---

## 1. Installation (Hostinger / any cPanel-style PHP host)

1. **Upload all files** to `public_html` via File Manager or FTP (keep folder structure).
2. **Create a MySQL database** in hPanel → Databases. Note name, user and password.
3. **Import `database.sql`** via phpMyAdmin (select your database → Import → upload the file).
4. **Edit `/includes/config.php`** and set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
   (`SITE_URL` should stay your real domain — it drives canonical/OG tags.)
5. **Folder permissions:** `/uploads/` → `755` (and its subfolders). PHP files stay `644`.
6. **Visit yoursite.com** — the frontend should render with the seeded demo content.
7. **Visit `yoursite.com/admin/login.php`**.
8. **Login:** `admin@thepietechnologies.com` / `Admin@123`
9. **IMMEDIATELY** go to *Settings → Change Password* and set your own.
10. **Add SMTP credentials** in *Settings → SMTP*, then click **Send Test Email**.
11. **Add your Gemini API key** in *Settings → Gemini API*, then click **Test Connection**
    (free key from Google AI Studio). This powers PIE Bot.
12. **Replace placeholder images** in `/assets/images/` with real photography
    (keep the same file names and the site updates everywhere instantly).
13. **Update agency info** in *Settings → Site Settings* (name, phone, email, address,
    WhatsApp number, Analytics/Pixel IDs, founder name, OG image).

> The site degrades gracefully: before the database is configured, pages still render and
> dynamic sections simply stay empty instead of throwing errors at visitors.

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
| `/blog.php`, `/blog-single.php` | Category filter, search, pagination (9/page); article page with share, related posts, moderated comments |
| `/about.php` | Story, mission/vision/values, team from DB, stats |
| `/contact.php` | Validated AJAX form → DB + admin email + branded client auto-reply + animated success state |
| `/sitemap.php`, `/robots.txt`, `/404.php`, `/maintenance.php`, `/privacy-policy.php`, `/terms.php` | Utility pages |

### Admin (`/admin/`)
`login.php` (bcrypt + 5-attempt/15-min lockout + CSRF), `index.php` (stat cards, 30-day line
chart, service bar chart, recent table, quick links, system status), `submissions.php`
(filters, bulk actions, CSV export, detail modal with notes + status), `blog.php` +
`blog-edit.php` (TinyMCE, uploads, SEO fields, auto slug + reading time), `resources.php`,
`portfolio.php` + `portfolio-edit.php` (Sortable.js reorder, stats/chart JSON editors),
`team.php` (drag-and-drop order), `testimonials.php`, `subscribers.php`, `comments.php`,
`settings.php` (SMTP / Gemini / Site / Social / Maintenance tabs with live test buttons),
`password.php`, `actions.php` (AJAX router), `export.php` (CSV).

### Chatbot (PIE Bot)
Widget on every page (footer). Client: `assets/js/chatbot.js` (history in `sessionStorage`,
skippable name/email lead capture). Backend: `includes/chatbot-api.php` routed through the
public `chatbot-api.php` endpoint — reads the key + editable system prompt from the
`settings` table, calls `gemini-1.5-flash`, stores leads in `chatbot_leads`, and falls back
to a friendly offline message if the API is unreachable or unconfigured.

---

## 3. Configuration reference (`/includes/config.php`)

| Constant | Meaning |
|---|---|
| `APP_ENV` | `production` hides PHP errors (default). Use `development` while debugging. |
| `DB_*` | MySQL credentials. |
| `SITE_URL` | Canonical domain for SEO/OG/sitemap. |
| `PRETTY_URLS` | `true` = extension-less links (needs the bundled `.htaccess`). Set `false` on hosts without mod_rewrite. |
| `BASE_URL` | Auto-detected, so sub-folder installs work too. |

Everything else (SMTP, Gemini, agency info, socials, analytics, maintenance mode, founder
name, default SEO tags) lives in the **settings table**, editable from the dashboard.

---

## 4. Email

`sendEmail()` tries, in order: **PHPMailer** (if you run `composer require phpmailer/phpmailer`
in the site root so `/vendor/autoload.php` exists), then the **built-in SMTP client**
(`includes/Mailer.php`, supports TLS/SSL + AUTH LOGIN), then PHP `mail()`.
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

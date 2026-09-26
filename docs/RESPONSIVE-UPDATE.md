# Responsive and visual update

> The subsequent [dark-theme refinement](DARK-THEME-REFINEMENTS.md) restores the original dark colors and supersedes the light-surface changes described below. Other fixes remain.

## Changes

- Kept the site's existing content and violet/cyan brand accents. Added a separate
  refinements stylesheet with lavender/cyan-tinted light page heroes, light home
  Services/FAQ sections, and light Blog/Library/Work collection surfaces.
- The home System remains pinned on desktop; at 900px and below it uses normal
  document flow and readable cards. Below 600px it is a single column. Reduced
  motion also uses the static layout. Resizing reconnects/disconnects the desktop
  observer rather than leaving mobile panels hidden.
- Home services, home FAQs and service-page accordions start closed. Closed
  content is inert; expanded content uses intrinsic grid height, not a fixed
  maximum that clips long answers.
- Mobile navigation follows the reference site's numbered links and grouped
  service structure without copying its colors. The menu has its own sticky
  brand/close header, an expandable Services group, project CTA and contact links.
  It scrolls independently and includes focus containment, Escape dismissal and
  viewport-change cleanup. Desktop navigation switches to the menu below 1240px
  so its labels do not crowd the brand/CTA. The desktop dropdown is centered.
- Alia has a local, optimized stock portrait (see IMAGE-CREDITS.md). The AI
  disclosure remains; the person in the photo is not presented as a staff member.
- A back-to-top button appears after 400px. It honors reduced motion and hides
  while navigation/chat is open.
- `/portfolio` and `/portfolio/` are explicitly routed before Apache directory
  handling. Previously the real portfolio directory bypassed the front controller
  and could return a directory-listing 403. Existing case-study paths remain.
- Changed CSS/JS URLs have filemtime versions to prevent old cached scripts from
  running against the new menu markup. Purge Hostinger page cache after deployment.

## Sample content

`database/samples.php` contains three sample articles, three downloadable resources
and three fictional portfolio projects. All are clearly labeled; no invented
client testimonials or performance results are represented as real work.

When a collection's table is empty (or the DB is unavailable), the public content
getters can render sample previews, including detail pages and existing PDF files.
An existing table containing drafts/inactive rows is NOT considered empty, and
filtering to zero matches does not inject unrelated samples. Sample detail pages
are noindex, and sample slugs are omitted from the sitemap.

**Admin → Settings → Site Settings → Add editable sample content** imports the
nine records into the existing CMS tables. Authentication and CSRF are required.
The import is transactional and skips existing sample slugs, preserving admin
edits and all other records. Repeating it does not duplicate content. The adjacent
checkbox disables automatic previews while database settings are available.
No live database was modified in this sandbox.

## Verification

- `php tests/regression.php`: 66 passing checks.
- `php tests/sample-content.php`: 35 passing checks, including detail resolution,
  valid PDF paths, filtering/pagination and import idempotency/admin-edit
  preservation. Import persistence checks used an isolated in-memory SQLite
  test schema, not production MySQL.
- `tests/responsive.cjs`: 97 Chromium checks at widths 320, 390, 768, 1280 and 1440.
  Verified overflow, closed/open accordions, static mobile System panels, menu
  header reachability/focus/Escape, dropdown bounds, back-to-top and image loading.
  Also checked Blog, Resources and Work sample cards at each width.
- Ten PHP fixture routes rendered without warnings, including sample detail pages.
- Additional manual browser checks covered landscape menu reachability, resizing
  back to desktop, and a fully visible expanded FAQ answer.
- JavaScript syntax and `git diff --check` passed.

PHP checks ran in a development-only WebAssembly runtime. Browser tests used
rendered PHP fixtures simulating an offline DB and the real local CSS/JS/images;
external CDN requests were blocked to exercise existing fallbacks. This is not
an Apache integration test or a test against the live reference site. Verify the
portfolio response after deploying `.htaccess` on Hostinger.

## Optional browser test usage

No JavaScript tooling is required on the production server. On a development
machine with Playwright installed and a populated staging/fixture site:

```sh
TPT_TEST_BASE_URL=https://your-staging-domain node tests/responsive.cjs
```

The suite expects the existing TPT navigation and at least three cards in each
sample collection. `TPT_CHROMIUM_EXECUTABLE` can specify a local Chromium binary;
`TPT_SCREENSHOT_DIR` overrides the default temporary screenshot directory.

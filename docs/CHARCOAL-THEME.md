# Charcoal theme refinement — 2026-09-27

Theme-only follow-up to DARK-THEME-REFINEMENTS.md. This supersedes that document's original near-black palette, not its layout or behavior changes.

## Changes

- Page background `#18191e`, alternating sections `#202127`, cards `#262830`, raised surfaces `#2e303a`.
- Main text `#f2f3f7`, secondary text `#bec1cd`, tertiary text/placeholders `#a2a8b8`; clearer neutral borders.
- Header, mobile menu, dropdowns, form fields, Alia window and preloader use the same charcoal family. Footer remains darker at `#16171c`, not black.
- Hero and other photo overlays are lighter. Work/portfolio/Why Us cards retain a localized, feathered caption scrim rather than shading the whole photograph heavily. Images themselves are unchanged.
- Violet/cyan and other brand accent tokens, CTA colors, dimensions, typography, spacing, breakpoints, transitions, keyframes, markup content and JavaScript remain unchanged. The client marquee's resting opacity is brighter; its hover transition is retained.
- Browser theme-color matches the page. Base stylesheet now has filemtime cache versioning, as the section/refinement stylesheets already did.

Only public CSS, header color/cache metadata, tests and documentation changed for this follow-up. No database, content, image, admin, integration or routing changes. Pre-edit copies of the three stylesheets and header were saved outside the repository in `/home/user/theme-baseline`.

## Verification

- 81 existing interface checks, with only the two authorized palette expectations updated.
- 97 existing responsive/interaction checks across 320, 390, 768, 1280 and 1440px.
- 26 new `tests/charcoal-theme.cjs` checks: all three neutral text tokens meet 4.5:1 against all four neutral surface tokens; brand accents unchanged; surface hierarchy, hero overlay, footer, Alia and field colors verified. This is a token contrast check, not a claim of a complete accessibility audit of every image/gradient/state.
- PHP WASM: 66 regression checks and 35 sample-content checks passed. Twenty-one public routes rendered without PHP warnings in an isolated DB-offline fixture.
- PostCSS comparison to pre-edit copies verified non-color layout/animation declarations were unchanged (except the explicitly brighter resting marquee opacity). JS syntax and `git diff --check` passed.
- Reviewed desktop hero/photo cards/footer and mobile contact screenshots.

The local preview is a static design fixture using existing offline/sample fallbacks, not the production database. POST submissions explicitly return 503; it is not a working integration demonstration. No live deployment to damaccleanor.com or real Apache/MySQL/SMTP/Gemini/payment validation was performed. Deploy the changed CSS and header together through the existing hosting workflow; purge any hosting/CDN cache if necessary.

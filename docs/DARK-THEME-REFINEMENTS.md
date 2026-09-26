# Dark-theme, compact-layout and industry update

**Palette follow-up:** [CHARCOAL-THEME.md](CHARCOAL-THEME.md) now supersedes the
near-black colors below with a softer dark-charcoal palette. All compact-layout
and interaction changes described here remain in place.

This supersedes the light-surface portion of RESPONSIVE-UPDATE.md. Other prior
responsive fixes, closed accordions, Alia portrait, back-to-top, sample content and
portfolio routing are preserved.

- Removed the added lavender/white surfaces and background-token overrides.
  The original dark page heroes, section colors and System palette apply again.
  Mobile System cards use the existing `--surface` token rather than white.
- Fixed the Contact icon collision: the wrapper now uses `.info-icon`, leaving
  SVG `.icon` paths free of the wrapper's padding/border/background styles.
- Replaced the large footer directory with a compact brand/social area, seven
  navigation links, contact details and legal links. Instagram, Facebook and
  LinkedIn defaults use the URLs already in database.sql. Existing Social Media
  settings override them; a saved blank value hides that platform. TikTok, X and
  YouTube appear only when configured. The contact social row uses the same helper.
- Reworked Industries to follow the reference homepage's “Who we grow” selector:
  eleven industry tabs and one detail panel with the original copy/service links.
  It supports click/tap, arrow keys, Home/End and ARIA tab relationships. Without
  JavaScript, all industry descriptions remain readable.
- Home founder section is a short note, 48–80px circular portrait and call-to-action.
  The portrait thumbnail is cropped from the project's existing founder image.
- About has three preview profiles when the team table is empty/offline, using
  existing seed identities: Ali Raza, Hina Shahid and Daniyal Khan. The founder name
  respects Settings. The preview is explicitly labeled; no real employment claim
  is added. Existing database profiles are not overwritten, and a table containing
  intentionally hidden profiles does not trigger the fallback. Missing photos use
  initials (or the existing founder portrait), not broken image links. Admin → Team
  remains the place to add/edit production profiles.

## Validation

- 81 interface checks passed at 320, 390, 768 and 1280px: dark backgrounds, tab
  interaction/keyboard controls, compact founder/footer dimensions, social links,
  SVG sizing, three preview team cards, loaded photos and horizontal overflow.
- Existing 97 responsive browser checks passed (mobile menu, System, accordions,
  back-to-top and content listing layouts).
- Existing PHP suites: 66 regression checks and 35 sample-content checks passed.
- Public pages rendered without PHP warnings in an isolated DB-offline fixture.
- Browser tests used real local assets and blocked CDNs to test fallbacks. They
  did not change or validate a production MySQL database or hosting configuration.

Optional test command on a development machine with Playwright:

```sh
TPT_TEST_BASE_URL=https://your-staging-site node tests/interface-updates.cjs
```

Use a staging database with the three starter profiles (or the offline preview).
No Node tooling is required by the production PHP website.

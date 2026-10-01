# Payments: server-verified confirmation, records dashboard and success popup

Everything below extends the existing payment integration. The administrator's
saved PayPal/Stripe code is still rendered verbatim and executed exactly once —
no code block is rewritten, duplicated or executed twice.

## What runs where

| Piece | Mode |
| --- | --- |
| `paypal_client_id` + `paypal_secret` stored | PayPal = **server-verified** (`core/PayPal.php`, `paypal-api.php`) |
| only `paypal_client_id` stored | PayPal = browser-only, exactly as before |
| `stripe_secret_key` stored | Stripe = **server-verified** (`core/Stripe.php`, `stripe-api.php`, `stripe-webhook.php`) |
| no `stripe_secret_key` | Stripe = browser-only, exactly as before (the saved code is untouched) |

The two providers decide independently. The "Server-verified payment" badge on
the Pay Online page appears when at least one **rendered** provider runs in
server mode.

## Files

Created:

| File | Why |
| --- | --- |
| `core/Stripe.php` | Stripe REST client: settings, key/webhook-secret helpers, `pieStripeServerReady()`, PaymentIntent create/retrieve, confirmation rules (succeeded + amount + USD), `Stripe-Signature` verification, scrubbing. |
| `stripe-api.php` | Browser-facing endpoint (`action=create` / `confirm` / `status`): POST + CSRF, creates PaymentIntents server-side, verifies with the Secret Key, records the payment once. |
| `stripe-webhook.php` | Signature-verified webhook for `payment_intent.succeeded` / `checkout.session.completed`; records payments completed after the tab closed. |
| `core/PaymentRecords.php` | `payment_records` data layer: idempotent `piePaymentRecord()`, dashboard filters/where/totals/list, deletes, CSV rows. |
| `admin/payment-records.php` | Admin → Payments dashboard: newest-first list, search, filters, totals, pagination, per-row delete with confirmation, bulk select + delete. |
| `admin/payment-records-export.php` | CSRF-protected CSV export of every record matching the current filters (formula-injection safe). |
| `database/migrations/009_stripe_server_and_payment_records.php` | The `payment_records` table + the two Stripe credential settings. |
| `tests/stripe-server.php`, `tests/payment-records.php` | Dependency-free tests for the server-side Stripe flow, the webhook, the duplicate protection and the dashboard. |

Changed:

| File | Why |
| --- | --- |
| `pay-online.php` | One two-column provider block per enabled gateway (fields + Terms left, buttons + "Powered by" right), stacked PayPal-first with a divider when both are enabled, per-provider server-mode badge. |
| `core/Payments.php` | Bridge: places the administrator's own nodes into the two columns (moves, never clones), exposes `window.TPT_STRIPE`, shows the accessible "Payment successful" popup once per payment, keeps filling `#tpt-payment-confirmation`. |
| `assets/css/refinements.css` | Two-column/stacked layout, 820px stack breakpoint, overflow guards for gateway iframes, popup styles. |
| `admin/payments.php` | Stripe Secret Key + Webhook Secret fields (password, never echoed, explicit remove), mode badges, webhook URL, warnings when a credential is missing. |
| `includes/admin-header.php` | "Payments" now points at the records dashboard; the settings screen is "Payment Settings". |
| `app/routes.php` | `/stripe-api`, `/api/stripe/*`, `/stripe-webhook`, `/api/stripe/webhook`. |
| `includes/init.php` | Payment webhooks are exempt from the browser-facing POST rate limit (they authenticate by signature, and a dropped delivery would lose a payment). |
| `core/Installer.php` | `payment_records` is an application table (fresh installs and the installer's table check). |
| `database.sql`, `database/schema-mysql.sql`, `database-upgrade.sql`, `database/seed.php` | Ship the table + the Stripe settings for new installs and upgrades. |
| `tests/admin-smoke.php`, `tests/harness/server.mjs` | Smoke-test the new screen (hyphenated page names) and forward the query string so admin filters/pagination work in the local preview. |

## SQL (migration 009, also in `database/schema-mysql.sql`)

```sql
CREATE TABLE IF NOT EXISTS payment_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(20) NOT NULL DEFAULT '',
  provider_transaction_id VARCHAR(150) DEFAULT NULL,
  payer_name VARCHAR(191) NOT NULL DEFAULT '',
  payer_email VARCHAR(191) NOT NULL DEFAULT '',
  service VARCHAR(191) NOT NULL DEFAULT '',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'USD',
  status VARCHAR(30) NOT NULL DEFAULT 'succeeded',
  verification_mode VARCHAR(20) NOT NULL DEFAULT 'server',
  raw_reference VARCHAR(255) NOT NULL DEFAULT '',
  ip_address VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_provider_transaction (provider, provider_transaction_id),
  KEY idx_payment_records_provider (provider),
  KEY idx_payment_records_status (status),
  KEY idx_payment_records_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('stripe_secret_key', ''),
  ('stripe_webhook_secret', '');
```

Apply with `php bin/cli.php migrate` (or re-run `database-upgrade.sql`).

## Stripe webhook endpoint(s) to register

Stripe → Developers → Webhooks → Add endpoint:

```
https://YOUR-DOMAIN/stripe-webhook
```

`https://YOUR-DOMAIN/api/stripe/webhook` is an alias of the same handler.
Subscribe to:

* `payment_intent.succeeded`
* `checkout.session.completed`

Copy the endpoint's signing secret (`whsec_…`) into **Admin → Payments →
Stripe Webhook Secret**. Without it the endpoint answers `503` and records
nothing; with a wrong signature it answers `400`.

## The success message

Two things happen when (and only when) a provider confirms the payment — a
PayPal capture the server verified, or a Stripe payment the server confirmed
(also when the webhook records it after the tab was closed):

1. **The popup** — `#tpt-pay-modal` is built by the bridge and opened by
   `window.TPT_PAYMENT_UI.showConfirmation()`: green check icon, **“Payment
   successful”**, `Thank you, <name>. Your payment has been confirmed by
   PayPal/Stripe.`, then a fact list with **Amount** (`$250.00 USD`),
   **Service**, **Provider** and **Payment reference**, and a **Close** button.
   It is a real dialog (`role="dialog"`, `aria-modal="true"`, labelled and
   described), focus moves to Close, `Esc`, the Close button and the overlay
   all close it, and focus returns to the page afterwards. It is responsive
   down to 390px.
2. **The confirmation area** — the existing `#tpt-payment-confirmation` box is
   filled with “Thank You, <name>! Your payment of $X USD was successfully
   completed. Payment Reference: …” and stays visible after the popup closes,
   so the buyer still has the receipt on the page.

The popup opens **once per payment**: the bridge remembers
`provider:reference`, so a re-rendered button, a duplicated SDK callback or a
replayed confirmation can never show it twice, and nothing is ever declared
successful before the server has verified it. A failed or cancelled payment
shows the gateway’s own message instead (`reportProblem` writes an explanatory
line next to the payment buttons) and records nothing.

Screenshots of the running preview are in `preview-shots/` (workspace root,
not in the repository): `success-popup.png`, `success-popup-mobile.png`,
`confirmation-area.png`, `providers-both.png`, `payment-records.png`.

## Test steps

1. **PayPal only** — Admin → Payments: enable PayPal, keep the Client ID +
   Secret, disable Stripe. Pay Online shows one full-width PayPal block; pay
   with a sandbox buyer account. The popup appears only after the server has
   captured and verified the order, and the row appears in Admin → Payments.
2. **Stripe only** — enable Stripe, save the Secret Key (`sk_test_…`) and the
   Webhook Secret, disable PayPal. Pay by card with `4242 4242 4242 4242`; the
   popup shows the PaymentIntent reference (`pi_…`) and the row appears in the
   dashboard. Remove the Secret Key and the page falls back to browser-only
   mode without any other change.
3. **Both enabled** — PayPal on top, Stripe below, each full width with its own
   label and divider; the badge "Server-verified payment" is shown. Narrow the
   window below 820px: fields + Terms first, buttons below.
4. **Failed / cancelled payment** — a cancelled PayPal window or a declined
   card (`4000 0000 0000 0002`) must not open the popup and must not create a
   record; the buyer sees the gateway's own message.
5. **Duplicate webhook** — in Stripe → Webhooks → the endpoint → *Send test
   webhook* or *Resend* the same `payment_intent.succeeded` event: the response
   says `"duplicate": true` and the dashboard still shows one row.
6. **Dashboard** — search by name/reference/service, filter by provider,
   status and date range, check the totals line, delete one record (confirm
   prompt) and bulk-delete several, then Export CSV with a filter applied and
   confirm the file only contains the filtered rows.

Run the offline suites with:

```bash
node tests/harness/cli.mjs tests/harness/lint.php
node tests/harness/cli.mjs tests/payments.php
node tests/harness/cli.mjs tests/paypal-server.php
node tests/harness/cli.mjs tests/stripe-server.php
node tests/harness/cli.mjs tests/payment-records.php
node tests/harness/cli.mjs tests/admin-smoke.php payment-records
```

## Secrets

`paypal_secret`, `stripe_secret_key` and `stripe_webhook_secret` are read only
on the server (`core/PayPal.php`, `core/Stripe.php`). They are never rendered
into HTML/JavaScript, never returned by an endpoint, never written to a log and
never shown in the dashboard (only "a secret is stored"). Log lines and API
error messages pass through `pieStripeScrub()`, which removes the stored values
and any key-shaped or `whsec_`-shaped token.

# Pay Online — server-side checkout

This is the existing TPT payment system. PayPal and Stripe are separate hosted-checkout providers; the Pay Online page uses one ordinary server-posted form and does not load either provider's JavaScript SDK.

## Security and checkout flow

1. The customer enters their name, email, optional phone/notes, an active service from Admin → Payments, and a USD amount.
2. The selected PHP endpoint checks the session-bound CSRF token, rate limit, active service, email and amount. Amounts must be from $0.01 to $1,000,000.00 USD with no more than two decimal places.
3. The server creates a `pending` row in the existing `payments` table, then creates a PayPal Order or Stripe Checkout Session with the matching amount and a random server-generated token.
4. The browser is redirected to PayPal or Stripe. No provider secret, API authorization header, PaymentIntent client secret, SDK configuration or browser success callback is sent to the public page.
5. On return, PHP retrieves/captures the payment directly from the provider and checks the provider status, amount, USD currency, transaction reference, and the attempt token/metadata.
6. Only a matching provider-confirmed payment is written to `payment_records` as `succeeded`, and its original `payments` row is marked `paid`. A failed or cancelled attempt never becomes a successful record. A signed Stripe webhook can independently record a completed Stripe payment if the customer closes the return page.
7. After the server confirmation, the Pay Online page shows exactly: `Thank You, [Name]! Your payment of $[Amount] USD was successfully completed.` and `Payment Reference: [Transaction ID]`. Failures and cancellations show a non-success notice.

`payment_records` is the existing Admin audit list. A successful record stores provider, provider transaction ID, customer name/email/phone, service, notes, amount, currency, status, verification mode, provider reference, IP address and recorded time. The existing Admin Dashboard shows recent confirmed payments and totals; Admin → Payments and Admin → Payment records provide the full list/export. Provider transaction IDs are unique per provider, making webhook/return retries idempotent.

## Configure credentials

Run the database upgrade first (see below), then sign in and open **Admin → Payments**.

### PayPal

- Create a PayPal REST app in PayPal Developer Dashboard.
- Set Client ID, Secret and **Sandbox** or **Live** in the PayPal panel. Use matching Sandbox app credentials for tests.
- Enable PayPal and save. The Secret is accepted by PHP, encrypted at rest, and never rendered back into an input or customer page. To rotate it, enter a new Secret; to remove it, use the explicit remove checkbox.

### Stripe

- Enter a Stripe **Secret Key** (`sk_test_…` for test mode or `sk_live_…` for live mode), enable Stripe, and save.
- A publishable key is not required: Checkout is hosted by Stripe and created by PHP.
- The optional Webhook Secret (`whsec_…`) enables signature-verified fallback recording if the customer does not return to the site. Register the URL shown in the Stripe panel and subscribe to `checkout.session.completed`, `checkout.session.async_payment_succeeded`, and `payment_intent.succeeded`. All accepted payment events are re-fetched from Stripe before a record is written.

PayPal and Stripe can be enabled independently or together. A gateway is shown to customers only when its toggle is on and the server credentials are valid. Both use the same existing Services list and shared Terms & Conditions URL. Services, records, and the payment form have not been replaced with a second system.

### Credential-encryption key

Set a private random `PAYMENT_ENCRYPTION_KEY` in `config/config.local.php` or the `TPT_PAYMENT_ENCRYPTION_KEY` server environment variable before migrating (see `config/config.local.php.example`). Keep it out of Git and outside the web root. If not set, TPT derives a stable encryption key from the existing private database connection settings and site URL. Keep the key stable: changing it makes already encrypted credentials unreadable; re-enter those credentials in Admin → Payments after a key change.

Stored values use the `enc:v1:` AES-256-GCM format and live in the existing `settings` table. Admin forms show only that a credential is present, never the value. Provider errors are scrubbed before they reach logs or visitors.

## Database upgrade

Run from the application root with the deployment's PHP/database configuration loaded:

```sh
php bin/cli.php migrate
```

Migration `010_server_side_payment_checkout.php` is re-runnable and:

- adds missing `payments.phone` and `payments.service` compatibility columns;
- adds `payer_phone` and `notes` to the existing `payment_records` table;
- seeds disabled gateway toggles without overwriting current settings;
- removes old custom SDK-code and unused publishable-key settings;
- encrypts any legacy plaintext PayPal/Stripe credential rows in place.

New-install schema files and `database/seed.php` include the compatible payment fields. The Services table and Admin payment-record dashboard remain shared with the rest of the site.

## Routes and implementation

- `pay-online.php` — shared form and server-set PRG notice.
- `paypal-api.php` — PayPal form POST, approval return/cancel, capture and confirmation.
- `stripe-api.php` — Stripe form POST, Checkout Session return/cancel, retrieval and confirmation.
- `stripe-webhook.php` — optional signed Stripe webhook, with provider re-fetch before recording.
- `core/Payments.php` — shared input validation, pending attempts, confirmed-record creation and notices.
- `core/PayPal.php`, `core/Stripe.php` — separate server-only provider clients.
- `core/PaymentCredentials.php` — AES-256-GCM credential encryption/decryption.
- `core/PaymentRecords.php` — existing Admin audit/list/export operations.
- `assets/css/refinements.css` — centered, responsive Pay Online layout and aligned provider buttons.

The application router exposes `/paypal-api`, `/stripe-api`, and `/stripe-webhook` (also `/api/stripe/webhook`). No custom payment-code editor, browser SDK injection, client-side capture handler, or publishable-key field is part of the new flow.

## Staging verification

After the migration and credential setup, verify with provider test accounts only:

1. **PayPal Sandbox:** enable PayPal only, use Sandbox app credentials, submit a valid amount/service, approve with a Sandbox buyer, and verify the exact server-confirmed message plus capture reference. Test user cancellation and a rejected/failed order; neither should be recorded as succeeded.
2. **Stripe test mode:** enable Stripe with an `sk_test_…` key, use Stripe's test card `4242 4242 4242 4242` with a future expiry and any CVC, then verify the message and PaymentIntent reference. Test a declined test card and return/cancel path; neither should show success.
3. Inspect Admin → Payments and Admin → Payment records: verify provider, customer name/email/phone, service, amount, USD, unique transaction reference, `succeeded` status and time. Confirm the legacy `payments` attempt is `paid` only after provider confirmation.
4. Test both providers enabled, each provider disabled, invalid/hidden service, malformed amount, bad CSRF, provider network failure and duplicate Stripe webhook delivery.
5. Check desktop, tablet and narrow-mobile widths. Inspect page source and browser requests: no `sk_`, `rk_`, PayPal Secret, OAuth token, Stripe client secret, PayPal/Stripe SDK, or browser payment callback should appear.
6. If using Stripe webhooks, confirm an invalid/missing signature is rejected, a valid event is verified against the Stripe API, and a duplicate event does not create a second record.

Local dependency-free checks are `php tests/payments.php`, `php tests/paypal-server.php`, `php tests/stripe-server.php`, and `php tests/payment-records.php`. They do not perform live provider requests. Run the real Sandbox/test-mode checklist on staging before enabling Live credentials. No live gateway transaction is part of the code-only test suite.

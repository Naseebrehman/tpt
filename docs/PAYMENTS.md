# Pay Online — server-side PayPal and Stripe

The Pay Online page creates hosted checkout sessions on the server for PayPal
and Stripe. The browser only posts the form to this site's same-origin API and
redirects to the validated provider checkout URL. PayPal capture and Stripe
session retrieval/webhook verification happen server-side. A payment is marked
`completed` only after the provider confirms its amount, currency and transaction
reference; the verified result is saved in `payment_records`.

## Server configuration

Payment secrets belong in the deployment-only `config/config.local.php` file
(or environment variables), never in page content, Admin fields, HTML or
JavaScript. The file is excluded from Git and blocked from web access.

```php
return array(
    // Existing database/site values omitted here.
    'PAYPAL_CLIENT_SECRET' => '...',
    'PAYPAL_ENVIRONMENT' => 'sandbox', // switch to 'live' only after staging checks
    'STRIPE_SECRET_KEY' => 'sk_test_...',
    'STRIPE_WEBHOOK_SECRET' => 'whsec_...',
);
```

The supported environment-variable equivalents are `TPT_PAYPAL_CLIENT_SECRET`,
`TPT_PAYPAL_ENVIRONMENT`, `TPT_STRIPE_SECRET_KEY` and
`TPT_STRIPE_WEBHOOK_SECRET`. The PayPal REST **Client ID** remains managed in
Admin → Payment Settings; the matching Client Secret stays server-only. Stripe
credentials are not editable in the dashboard and are never returned by an API.
The Admin payment page displays readiness without showing secret values.

Configure the Stripe webhook to this same-site endpoint:

```text
https://your-domain.example/api/payments/stripe/webhook
```

Subscribe to `checkout.session.completed` and
`checkout.session.async_payment_succeeded`. Signature and timestamp are
verified with the server-only webhook signing secret. PayPal uses the REST API
with server-side OAuth and captures its approved order on the signed-state
return to this site.

## Checkout and reset behavior

1. The page validates name/business name, configured service and USD amount.
2. A CSRF-protected same-origin POST creates a pending server-side payment
   record and asks the selected provider to create a hosted checkout.
3. The browser redirects to PayPal or Stripe. This page does not load the
   PayPal JavaScript SDK, call browser-side capture, or load payment snippets.
4. On return, the server validates a high-entropy state token and confirms the
   transaction directly with the provider. Stripe webhooks provide an
   additional signed confirmation path.
5. Only after the server reports a confirmed, recorded payment does the page
   clear the name, service, amount, validation state and button state and show
   the success popup. On cancel or an unconfirmed result, the form values are
   restored so the customer can review them instead of losing them.

The `payment_records` ledger stores the service, amount, provider, local
reference, provider transaction reference and payment status. It does not store
card numbers or PayPal login details. Admin → Payment Settings shows recent
records and preserves the existing service manager.

## Database upgrade

Back up the database, then run:

```sh
php bin/cli.php migrate
```

Migration 011 is now a compatibility no-op; migration 012 additively creates
the payment ledger and event tables. No portfolio, contact, payment or other
existing rows are dropped by these changes. `database/schema-mysql.sql` and the
legacy import/upgrade SQL contain the same payment schema for fresh installs.

## Verification and staging

Local checks cover the rendered form, CSRF wiring, provider endpoints, secret
non-disclosure, signed Stripe webhook validation, amount validation and the
payment-success reset code. They do not charge a card or call provider APIs.
Before using live credentials, test both providers on staging:

- Use PayPal Sandbox and Stripe test-mode credentials.
- Confirm success, cancellation and failed/unconfirmed returns.
- Confirm only server-verified success produces a completed payment record and
  opens the confirmation popup.
- Confirm close and outside-click hide the popup and restore normal scrolling.
- Confirm successful payment clears every form field and prior validation
  state; cancellation keeps/restores the entered details.
- Verify that no secret, browser payment SDK, browser capture code or payment
  snippet appears in public source/network requests.

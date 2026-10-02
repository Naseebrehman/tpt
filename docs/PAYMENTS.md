# Pay Online — PayPal JavaScript SDK

The payment page (`pay-online.php`) offers **PayPal only**. It uses the official
PayPal JavaScript SDK with the Client ID saved in the application settings.
There is no secret, no server-side capture, no webhook, no payment API and no
payment record: the order is created, approved and confirmed by PayPal in the
browser, and the page then shows the success popup with the amount, service and
PayPal reference.

## Configure the Client ID

1. Sign in to the admin dashboard and open **Admin → Payment Settings**.
2. Paste the **Client ID** of your PayPal REST app (PayPal Developer Dashboard
   → Apps & Credentials).
3. Press **Save**.

The Client ID is stored in the existing `settings` table under
`paypal_client_id` and is read live when the page renders, so changing it takes
effect on the next page load — nothing is hard-coded and no deployment or cache
clear is needed.

A Client Secret is **not** required and must not be entered: this integration
never talks to the PayPal REST API from the server. When no Client ID is saved,
the Pay Online page shows a “payments are currently unavailable” notice and
loads no SDK or button.

The same admin screen keeps the existing **Payment form services** manager that
feeds the service dropdown on the payment page.

## What the page does

1. The visitor enters their name, email, optional phone/notes, an active service
   and a USD amount.
2. The PayPal SDK button validates those fields in the browser and creates the
   order via `actions.order.create()` with the entered amount and service.
3. PayPal’s own secure checkout opens. Card/PayPal details are entered on
   PayPal, never on this site.
4. On approval `actions.order.capture()` completes the payment and the page
   shows the PayPal success popup (`#tpt-modal`) plus the inline confirmation
   with the amount, service and reference.

## What is deliberately not part of this integration

- PayPal Client Secret or any other PayPal credential
- Server-side PayPal confirmation/capture
- PayPal webhooks
- Payment verification or status tracking
- Payment database tables, records, history, dashboard or CSV export
- Payment APIs or confirmation endpoints

`core/Payments.php` only reads the settings written by the admin screen and
builds the SDK URL (`https://www.paypal.com/sdk/js?client-id=…&currency=USD`).

## Database upgrade

Existing installations that still carry the old Stripe settings, encrypted
PayPal secrets, payment email templates or `payments`/`payment_records` tables
are cleaned up by `database/migrations/011_paypal_sdk_only.php`:

```sh
php bin/cli.php migrate
```

The migration keeps `paypal_client_id`, drops the legacy payment tables and
deletes every Stripe/secret setting. Customer payment details were never stored
by this site, so no payment data needs to be preserved.

## Staging verification

1. Save a PayPal **Sandbox** Client ID, open `/pay-online`, and confirm the
   PayPal button renders.
2. Pay with a sandbox buyer account and confirm the PayPal success popup shows
   the amount, service and reference.
3. Change the Client ID in Admin → Payment Settings and reload the page; confirm
   the SDK now loads with the new Client ID.
4. Clear the Client ID and confirm the page shows the unavailable notice and
   loads no PayPal SDK.
5. Inspect page source and browser requests: only the public Client ID appears —
   no secret, no server endpoint, no payment record request.

`node tests/harness/cli.mjs tests/payment-page-render.php` renders the real page
against an in-memory fixture and checks the Client-ID wiring, the single PayPal
option and the absence of secrets.

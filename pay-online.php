<?php
/** Customer-owned payment page. The site intentionally performs no gateway
 * requests, captures, verification, or payment persistence: the page renders
 * only the custom PayPal / Stripe code saved by the administrator in
 * Admin → Payments, verbatim and exactly once on load.
 *
 * The only values substituted into that code are the admin-controlled
 * integration points — PayPal Client ID, the shared Terms & Conditions URL and
 * the shared Services list (see core/Payments.php). Everything else is echoed
 * exactly as the administrator saved it. */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'Pay Online — The Pie Technologies';
$metaDesc = 'Complete your payment securely through our online payment provider.';
$activeNav = 'pay';

$paypalEnabled = pieIsPayPalEnabled();
$stripeEnabled = pieIsStripeEnabled();
$paypalSdkCode = piePayPalSdkCode();
$stripeSdkCode = pieStripeSdkCode();

/* Render a provider block only when it is enabled AND has saved code.
 * Both enabled → both complete code blocks render; both disabled (or no
 * code saved) → the unavailable message. The saved code is echoed exactly
 * as stored — never escaped, sanitised or rewritten — apart from the
 * documented {{PLACEHOLDER}} integration points. */
$paypalBlock = $paypalEnabled && $paypalSdkCode !== '';
$stripeBlock = $stripeEnabled && $stripeSdkCode !== '';
$anyBlock    = ($paypalBlock || $stripeBlock);

/* Providers actually rendered, so the shared bridge only wires what exists. */
$renderedProviders = array();
if ($paypalBlock) { $renderedProviders[] = 'paypal'; }
if ($stripeBlock) { $renderedProviders[] = 'stripe'; }

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Pay Online</p>
    <h1>Make a payment.</h1>
    <p class="lead">Complete your payment securely through our online payment provider.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="contact-grid payment-layout">
      <div class="contact-panel pay-panel">
        <h2>Payment details</h2>
        <p class="sub">Your payment provider handles all payment communication. This site does not store or process payment details.</p>

        <?php if ($anyBlock): ?>
          <?php
          /* One shared bridge, emitted before the first payment block so the
             PayPal SDK is intercepted before it loads. It publishes the shared
             Services list, applies the shared Terms URL and makes sure PayPal
             never requests a shipping address. */
          echo piePaymentBridge($renderedProviders);
          ?>
          <?php if ($paypalBlock): ?>
            <!-- Administrator's complete PayPal implementation — rendered verbatim, executed once. -->
            <div class="pay-custom-code" id="paypal-payment-code" data-payment-provider="paypal"><?= pieRenderPaymentCode($paypalSdkCode, 'paypal') ?></div>
          <?php endif; ?>
          <?php if ($stripeBlock): ?>
            <!-- Administrator's complete Stripe implementation — rendered verbatim, executed once. -->
            <div class="pay-custom-code" id="stripe-payment-code" data-payment-provider="stripe"><?= pieRenderPaymentCode($stripeSdkCode, 'stripe') ?></div>
          <?php endif; ?>
        <?php else: ?>
          <div class="form-status show" style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#fca5a5;padding:18px 20px;border-radius:12px;margin:20px 0;">
            <strong style="display:block;margin-bottom:6px;font-size:1rem;color:#fecaca;">Online payments are currently unavailable.</strong>
            <span>Please contact our team at <a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>" style="color:#a78bfa;text-decoration:underline;"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a> or call <a href="tel:<?= esc(getSetting('site_phone', '+1 (213) 257 8242')) ?>" style="color:#a78bfa;text-decoration:underline;"><?= esc(getSetting('site_phone', '+1 (213) 257 8242')) ?></a> to arrange alternative payment.</span>
          </div>
        <?php endif; ?>
      </div>
      <aside class="contact-side">
        <div class="info-card"><span class="info-icon"><?= icon('lock', 22) ?></span><span><strong>Provider-managed checkout</strong><p>Payment credentials and processing are handled entirely by your payment provider.</p></span></div>
        <div class="info-card"><span class="info-icon"><?= icon('mail', 22) ?></span><span><strong>Need help?</strong><p>Contact <a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a>.</p></span></div>
      </aside>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

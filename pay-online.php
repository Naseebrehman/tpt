<?php
/** Customer-owned payment page. The page renders the custom PayPal / Stripe
 * code saved by the administrator in Admin → Payments (verbatim, exactly once
 * on load) and keeps the existing gateway IDs, toggles, Services list and the
 * ONE shared Terms & Conditions URL.
 *
 * When a PayPal Client ID AND Secret are stored, the shared bridge behind this
 * page routes the PayPal order through the server (paypal-api.php) so the
 * payment is verified with PayPal on the server before it is ever reported as
 * successful. The Secret is never rendered into this HTML or its JavaScript.
 *
 * The only values substituted into the administrator's code are the documented
 * integration points — PayPal Client ID, shared Terms URL and shared Services
 * list (see core/Payments.php). Everything else is echoed exactly as saved. */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'Pay Online — The Pie Technologies';
$metaDesc = 'Complete your payment securely through our online payment provider.';
$activeNav = 'pay';

$paypalEnabled = pieIsPayPalEnabled();
$stripeEnabled = pieIsStripeEnabled();
$paypalSdkCode = piePayPalSdkCode();
$stripeSdkCode = pieStripeSdkCode();
$serverVerified = piePayPalServerReady();

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

$bothProviders = ($paypalBlock && $stripeBlock);
$termsUrl      = pieTermsUrl();
$supportEmail  = getSetting('site_email', 'info@thepietechnologies.com');
$supportPhone  = getSetting('site_phone', '');
$paymentFaq = array(
    array(
        'q' => 'Which payment methods can I use?',
        'a' => 'PayPal and Stripe. Both are managed from the TPT dashboard, so the options shown on this page are the ones currently enabled for online payment.',
    ),
    array(
        'q' => 'Which currency are payments taken in?',
        'a' => 'Every payment on this page is processed in US dollars (USD). The amount you enter is the amount charged.',
    ),
    array(
        'q' => 'Is my payment secure?',
        'a' => 'Yes. You pay on your provider’s own secure checkout — PayPal or Stripe. Card and PayPal details are never entered on, or stored by, this website.',
    ),
    array(
        'q' => 'How do I know my payment went through?',
        'a' => 'Once the payment is confirmed you will see a thank-you message on this page with the amount and a payment reference. Nothing is shown as successful before that confirmation arrives.',
    ),
    array(
        'q' => 'What can I pay for?',
        'a' => 'Any service in the list above — the same list our team manages in the dashboard. If your item is not listed, choose “Others” and describe it in the project notes.',
    ),
    array(
        'q' => 'Which terms apply to my payment?',
        'a' => 'By continuing with your payment you agree to our Terms & Conditions. They are linked under the payment form and in the site footer.',
    ),
    array(
        'q' => 'What if something looks wrong?',
        'a' => 'Contact the team before paying again, quoting any reference shown above. We will check the payment with the provider and confirm it with you.',
    ),
);

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero pay-hero">
  <div class="container">
    <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Pay Online</p>
    <h1>Make a payment.</h1>
    <p class="lead">Complete your payment securely in USD through PayPal or Stripe — processed by the provider, confirmed by our server.</p>
    <ul class="pay-badges" aria-label="Payment facts">
      <?php if ($paypalBlock): ?><li><?= icon('check', 14) ?> PayPal</li><?php endif; ?>
      <?php if ($stripeBlock): ?><li><?= icon('check', 14) ?> Stripe</li><?php endif; ?>
      <li><?= icon('check', 14) ?> USD</li>
      <?php if ($serverVerified): ?><li><?= icon('lock', 14) ?> Server-verified payment</li><?php endif; ?>
    </ul>
  </div>
</section>

<section class="section pay-section">
  <div class="container">
    <div class="pay-shell">
      <div class="contact-panel pay-panel">
        <div class="pay-panel-head">
          <h2>Payment details</h2>
          <p class="sub">Choose your provider, enter the amount, and pay. Payment communication happens on the provider’s own secure checkout — this site never sees or stores your card or PayPal credentials.</p>
        </div>

        <!-- Confirmed payments only: filled in after the server has verified the capture. -->
        <div class="pay-confirmation" id="tpt-payment-confirmation" role="status" aria-live="polite" hidden></div>

        <?php if ($anyBlock): ?>
          <?php
          /* One shared bridge, emitted before the first payment block so the
             PayPal SDK is intercepted before it loads. It publishes the shared
             Services list, applies the shared Terms URL, replaces the Terms
             checkbox with the small legal line, forces NO_SHIPPING, verifies
             the capture on the server and reports the confirmed payment. */
          echo piePaymentBridge($renderedProviders);
          ?>
          <div class="pay-providers<?= $bothProviders ? ' is-split' : '' ?>">
            <?php if ($paypalBlock): ?>
              <div class="pay-provider" data-provider="paypal">
                <?php if ($bothProviders): ?><p class="pay-provider-label eyebrow"><?= icon('card', 14) ?> Pay with PayPal</p><?php endif; ?>
                <!-- Administrator's complete PayPal implementation — rendered verbatim, executed once. -->
                <div class="pay-custom-code" id="paypal-payment-code" data-payment-provider="paypal"><?= pieRenderPaymentCode($paypalSdkCode, 'paypal') ?></div>
              </div>
            <?php endif; ?>
            <?php if ($stripeBlock): ?>
              <div class="pay-provider" data-provider="stripe">
                <?php if ($bothProviders): ?><p class="pay-provider-label eyebrow"><?= icon('card', 14) ?> Pay by card (Stripe)</p><?php endif; ?>
                <!-- Administrator's complete Stripe implementation — rendered verbatim, executed once. -->
                <div class="pay-custom-code" id="stripe-payment-code" data-payment-provider="stripe"><?= pieRenderPaymentCode($stripeSdkCode, 'stripe') ?></div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Small legal line (the Terms checkbox is gone). -->
          <p class="pay-terms-note" data-tpt-terms-page="1">By continuing with your payment, you agree to our <a href="<?= esc($termsUrl) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</p>
        <?php else: ?>
          <div class="form-status show" style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#fca5a5;padding:16px 18px;border-radius:12px;margin:16px 0;">
            <strong style="display:block;margin-bottom:6px;font-size:1rem;color:#fecaca;">Online payments are currently unavailable.</strong>
            <span>Please contact our team at <a href="mailto:<?= esc($supportEmail) ?>" style="color:#a78bfa;text-decoration:underline;"><?= esc($supportEmail) ?></a><?php if ($supportPhone !== ''): ?> or call <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $supportPhone)) ?>" style="color:#a78bfa;text-decoration:underline;"><?= esc($supportPhone) ?></a><?php endif; ?> to arrange alternative payment.</span>
          </div>
        <?php endif; ?>
      </div>

      <aside class="contact-side pay-side">
        <!-- One helper card only: need help / having difficulties. -->
        <div class="info-card pay-help-card">
          <span class="info-icon"><?= icon('mail', 22) ?></span>
          <span>
            <strong>Need help or having difficulties?</strong>
            <p>If a payment does not go through, something looks wrong, or you are not sure which option to choose — stop before paying again and talk to us first.</p>
            <p>Email <a href="mailto:<?= esc($supportEmail) ?>"><?= esc($supportEmail) ?></a><?php if ($supportPhone !== ''): ?><br>Call <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $supportPhone)) ?>"><?= esc($supportPhone) ?></a><?php endif; ?></p>
            <p>Quote the amount and any reference shown and we will check the payment with the provider for you.</p>
            <p><a class="link-arrow" href="<?= url('contact') ?>">Send us the details <?= icon('arrow-r', 14) ?></a></p>
          </span>
        </div>
      </aside>
    </div>
  </div>
</section>

<!-- ===================== COMPACT PAYMENT FAQ ===================== -->
<section class="section pay-faq-section" aria-labelledby="paymentFaqHeading">
  <div class="container">
    <div class="pay-faq-wrap">
      <div class="section-head pay-faq-head" data-aos="fade-up">
        <p class="eyebrow">Payment FAQ</p>
        <h2 class="section-title" id="paymentFaqHeading">Questions about paying.</h2>
      </div>
      <div class="pay-faq-grid" data-accordion="single">
        <?php foreach ($paymentFaq as $faqItem): ?>
        <div class="acc-item">
          <button class="acc-head" type="button" aria-expanded="false">
            <span class="acc-title"><?= esc($faqItem['q']) ?></span>
            <span class="acc-icon"><?= icon('plus', 16) ?></span>
          </button>
          <div class="acc-body" inert>
            <p class="acc-copy"><?= esc($faqItem['a']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="pay-faq-foot">Still unsure? <a class="link-arrow" href="<?= url('contact') ?>">Ask the team <?= icon('arrow-r', 16) ?></a></p>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

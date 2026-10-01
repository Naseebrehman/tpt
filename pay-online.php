<?php
/** Pay Online. The form posts directly to an independent server-side gateway
 * endpoint; no PayPal/Stripe browser SDK, payment JavaScript or success callback
 * is loaded on this page.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'Pay Online — The Pie Technologies';
$metaDesc = 'Pay securely through PayPal or Stripe. Every payment is confirmed by our server.';
$activeNav = 'pay';

$paymentStorageAvailable = piePaymentStorageReady();
$paypalAvailable = $paymentStorageAvailable && pieIsPayPalEnabled() && piePayPalServerReady();
$stripeAvailable = $paymentStorageAvailable && pieIsStripeEnabled() && pieStripeServerReady();
$anyGateway = $paypalAvailable || $stripeAvailable;
$firstGateway = $paypalAvailable ? 'paypal-api' : 'stripe-api';
$termsUrl = pieTermsUrl();
$supportEmail = getSetting('site_email', 'info@thepietechnologies.com');
$supportPhone = getSetting('site_phone', '');
$paymentNotice = piePaymentConsumeNotice();
$paymentServices = piePaymentServices();

$paymentFaq = array(
    array('q' => 'Which payment methods can I use?', 'a' => 'Use PayPal or a card through Stripe. Only payment methods currently enabled in our secure server-side checkout are shown.'),
    array('q' => 'Which currency are payments taken in?', 'a' => 'Payments are processed in US dollars (USD). The amount you enter is sent to the selected payment provider by our server.'),
    array('q' => 'Is my payment secure?', 'a' => 'Your payment is completed on PayPal or Stripe’s secure hosted checkout. This website does not load their payment SDKs or collect your card details.'),
    array('q' => 'How do I know my payment went through?', 'a' => 'Our server confirms the payment with the provider before showing a thank-you message and payment reference. A click or redirect alone is never treated as success.'),
    array('q' => 'What can I pay for?', 'a' => 'Choose a service from the list managed by our team in Admin → Payments. If you select “Others”, describe your payment in the notes field.'),
    array('q' => 'Which terms apply to my payment?', 'a' => 'By continuing with your payment you agree to our Terms & Conditions, linked below the payment options and in the site footer.'),
    array('q' => 'What if something looks wrong?', 'a' => 'Do not submit another payment if you are unsure. Contact our team with the amount and any payment reference so we can check with the provider.'),
);

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero pay-hero">
  <div class="container">
    <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Pay Online</p>
    <h1>Pay online, securely.</h1>
    <p class="lead">A straightforward checkout, server-verified through PayPal or Stripe.</p>
    <ul class="pay-badges" aria-label="Payment facts">
      <?php if ($paypalAvailable): ?><li><?= icon('check', 14) ?> PayPal</li><?php endif; ?>
      <?php if ($stripeAvailable): ?><li><?= icon('check', 14) ?> Stripe</li><?php endif; ?>
      <li><?= icon('check', 14) ?> USD</li>
      <li><?= icon('lock', 14) ?> Server-confirmed</li>
    </ul>
  </div>
</section>

<section class="section pay-section" aria-labelledby="paymentHeading">
  <div class="container">
    <div class="contact-panel pay-panel">
      <div class="pay-panel-head">
        <div>
          <p class="eyebrow">Secure checkout</p>
          <h2 id="paymentHeading">Payment details</h2>
          <p class="sub">Enter your details once, then choose a provider. Card and PayPal credentials stay on the provider’s hosted checkout.</p>
        </div>
        <span class="pay-secure-mark"><?= icon('lock', 18) ?> Protected checkout</span>
      </div>

      <div class="pay-confirmation<?= $paymentNotice ? ($paymentNotice['type'] === 'success' ? ' is-success' : ($paymentNotice['type'] === 'cancelled' ? ' is-cancelled' : ' is-error')) : '' ?>" id="tpt-payment-confirmation" role="status" aria-live="polite"<?= $paymentNotice ? '' : ' hidden' ?>>
        <?php if ($paymentNotice && $paymentNotice['type'] === 'success'): ?>
          <strong>Thank You, <?= esc($paymentNotice['name'] !== '' ? $paymentNotice['name'] : 'Customer') ?>! Your payment of $<?= esc($paymentNotice['amount']) ?> USD was successfully completed.</strong>
          <span>Payment Reference: <code><?= esc($paymentNotice['reference']) ?></code></span>
        <?php elseif ($paymentNotice): ?>
          <strong><?= $paymentNotice['type'] === 'cancelled' ? 'Payment cancelled' : 'Payment not completed' ?></strong>
          <span><?= esc($paymentNotice['message']) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($anyGateway): ?>
      <form id="payment-form" class="pay-layout" method="post" action="<?= esc(url($firstGateway)) ?>">
        <?= csrfField() ?>
        <input type="hidden" name="payment_action" value="start">

        <div class="pay-form-fields">
          <div class="pay-field">
            <label for="payment-name">Name / Business name <span aria-hidden="true">*</span></label>
            <input id="payment-name" name="name" type="text" maxlength="150" autocomplete="name" placeholder="Your name or business name" required>
          </div>
          <div class="pay-field">
            <label for="payment-email">Email address <span aria-hidden="true">*</span></label>
            <input id="payment-email" name="email" type="email" maxlength="150" autocomplete="email" placeholder="you@example.com" required>
          </div>
          <div class="pay-field">
            <label for="payment-phone">Phone <span class="optional">Optional</span></label>
            <input id="payment-phone" name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="Phone number">
          </div>
          <div class="pay-field">
            <label for="payment-service">Service <span aria-hidden="true">*</span></label>
            <select id="payment-service" name="service" required>
              <option value="">Choose a service</option>
              <?php foreach ($paymentServices as $serviceName): ?>
                <option value="<?= esc($serviceName) ?>"><?= esc($serviceName) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="pay-field">
            <label for="payment-amount">Amount (USD) <span aria-hidden="true">*</span></label>
            <div class="pay-amount-input"><span aria-hidden="true">$</span><input id="payment-amount" name="amount" type="number" min="0.01" max="1000000" step="0.01" inputmode="decimal" placeholder="500.00" required></div>
            <small>Enter an amount between $0.01 and $1,000,000.00.</small>
          </div>
          <div class="pay-field pay-field-notes">
            <label for="payment-notes">Notes <span class="optional">Optional</span></label>
            <textarea id="payment-notes" name="notes" maxlength="2000" rows="3" placeholder="Add an invoice, project or payment note"></textarea>
          </div>
        </div>

        <aside class="pay-checkout-options" aria-label="Choose a secure payment method">
          <div class="pay-checkout-copy">
            <p class="eyebrow">Payment method</p>
            <h3>Choose how to pay</h3>
            <p>Your selected amount and service are sent securely to the provider. Our server confirms the final status before recording the payment.</p>
          </div>
          <div class="pay-gateway-actions">
            <?php if ($paypalAvailable): ?>
            <button class="pay-submit pay-submit-paypal" type="submit" name="payment_action" value="start" formaction="<?= esc(url('paypal-api')) ?>">
              <span class="pay-submit-mark" aria-hidden="true"><?= icon('card', 19) ?></span>
              <span class="pay-submit-label"><strong>Continue with PayPal</strong><small>Secure hosted checkout</small></span>
              <span class="pay-submit-arrow" aria-hidden="true"><?= icon('arrow-r', 18) ?></span>
            </button>
            <?php endif; ?>
            <?php if ($stripeAvailable): ?>
            <button class="pay-submit pay-submit-stripe" type="submit" name="payment_action" value="start" formaction="<?= esc(url('stripe-api')) ?>">
              <span class="pay-submit-mark" aria-hidden="true"><?= icon('card', 19) ?></span>
              <span class="pay-submit-label"><strong>Pay by card</strong><small>Secure checkout by Stripe</small></span>
              <span class="pay-submit-arrow" aria-hidden="true"><?= icon('arrow-r', 18) ?></span>
            </button>
            <?php endif; ?>
          </div>
          <p class="pay-terms-note">By continuing with your payment, you agree to our <a href="<?= esc($termsUrl) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</p>
          <p class="pay-no-card"><?= icon('lock', 14) ?> No card or PayPal credentials are entered on this website.</p>
        </aside>
      </form>
      <?php else: ?>
      <div class="pay-unavailable" role="status">
        <strong>Online payments are currently unavailable.</strong>
        <p>Please contact our team at <a href="mailto:<?= esc($supportEmail) ?>"><?= esc($supportEmail) ?></a><?php if ($supportPhone !== ''): ?> or call <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $supportPhone)) ?>"><?= esc($supportPhone) ?></a><?php endif; ?> to arrange payment.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section pay-help-section" aria-labelledby="payHelpHeading">
  <div class="container">
    <div class="founder-card founder-compact pay-help-card" data-aos="fade-up">
      <span class="founder-avatar pay-help-avatar" aria-hidden="true"><?= icon('mail', 30) ?></span>
      <div class="founder-note">
        <p class="eyebrow">Payment support</p>
        <h2 id="payHelpHeading">Need help or having difficulties?</h2>
        <p>If a payment does not go through or something looks wrong, stop before paying again and talk to us first. Quote the amount and any reference shown, and we will check the payment with the provider.</p>
        <span class="founder-sig">Email <a href="mailto:<?= esc($supportEmail) ?>"><?= esc($supportEmail) ?></a><?php if ($supportPhone !== ''): ?> · Call <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $supportPhone)) ?>"><?= esc($supportPhone) ?></a><?php endif; ?></span>
      </div>
      <a class="btn btn-primary founder-book" href="<?= url('contact') ?>">Send us the details <?= icon('arrow-r', 18) ?></a>
    </div>
  </div>
</section>

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
          <div class="acc-body" inert><p class="acc-copy"><?= esc($faqItem['a']) ?></p></div>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="pay-faq-foot">Still unsure? <a class="link-arrow" href="<?= url('contact') ?>">Ask the team <?= icon('arrow-r', 16) ?></a></p>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

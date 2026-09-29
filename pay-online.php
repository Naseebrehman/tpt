<?php
/** Customer-owned SDK payment page. The site intentionally performs no gateway
 * requests, captures, verification, or payment persistence. */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'Pay Online — The Pie Technologies';
$metaDesc = 'Enter your payment details and continue using your chosen payment provider.';
$activeNav = 'pay';
$payServices = piePaymentServices();

$paypalEnabled = pieIsPayPalEnabled();
$stripeEnabled = pieIsStripeEnabled();
$paypalSdkCode = piePayPalSdkCode();
$stripeSdkCode = pieStripeSdkCode();
$anyEnabled    = ($paypalEnabled || $stripeEnabled);

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Pay Online</p>
    <h1>Make a payment.</h1>
    <p class="lead">Enter your details, choose a service, then continue with your preferred payment provider.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="contact-grid payment-layout">
      <div class="contact-panel pay-panel">
        <h2>Payment details</h2>
        <p class="sub">Your payment SDK handles all provider communication. This site does not store or process payment details.</p>

        <?php if (!$anyEnabled): ?>
          <div class="form-status show" style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#fca5a5;padding:18px 20px;border-radius:12px;margin:20px 0;">
            <strong style="display:block;margin-bottom:6px;font-size:1rem;color:#fecaca;">Online payments are currently unavailable.</strong>
            <span>Payment methods are currently offline. Please contact our team at <a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>" style="color:#a78bfa;text-decoration:underline;"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a> or call <a href="tel:<?= esc(getSetting('site_phone', '+1 (213) 257 8242')) ?>" style="color:#a78bfa;text-decoration:underline;"><?= esc(getSetting('site_phone', '+1 (213) 257 8242')) ?></a> to arrange alternative payment.</span>
          </div>
        <?php endif; ?>

        <form id="payForm" class="payment-form" data-payment-form novalidate>
          <div class="form-grid">
            <div class="field"><label for="pName">Full name <span class="req">*</span></label><input id="pName" name="name" type="text" required maxlength="150" autocomplete="name" <?= !$anyEnabled ? 'disabled' : '' ?>></div>
            <div class="field"><label for="pEmail">Email address <span class="req">*</span></label><input id="pEmail" name="email" type="email" required maxlength="150" autocomplete="email" <?= !$anyEnabled ? 'disabled' : '' ?>></div>
            <div class="field"><label for="pPhone">Phone number</label><input id="pPhone" name="phone" type="tel" maxlength="30" autocomplete="tel" <?= !$anyEnabled ? 'disabled' : '' ?>></div>
            <div class="field"><label for="pService">Service <span class="req">*</span></label>
              <select id="pService" name="service" required <?= !$anyEnabled ? 'disabled' : '' ?>><option value="">Select a service</option><?php foreach ($payServices as $service): ?><option value="<?= esc($service) ?>"><?= esc($service) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label for="pAmount">Amount (USD) <span class="req">*</span></label><input id="pAmount" name="amount" type="number" min="0.01" step="0.01" required inputmode="decimal" placeholder="0.00" <?= !$anyEnabled ? 'disabled' : '' ?>></div>
            <div class="field full"><label class="pay-terms"><input type="checkbox" name="terms" value="1" required <?= !$anyEnabled ? 'disabled' : '' ?>><span>I agree to the <a href="<?= url('terms') ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>. <span class="req">*</span></span></label></div>

            <?php if ($paypalEnabled && $stripeEnabled): ?>
              <!-- Both PayPal and Stripe enabled: let the customer choose -->
              <div class="field full">
                <label for="paymentProvider">Payment method <span class="req">*</span></label>
                <select id="paymentProvider" name="provider" required>
                  <option value="paypal">PayPal</option>
                  <option value="stripe">Stripe</option>
                </select>
              </div>
            <?php elseif ($paypalEnabled): ?>
              <!-- PayPal only enabled -->
              <div class="field full">
                <label>Payment method</label>
                <input type="hidden" id="paymentProvider" name="provider" value="paypal">
                <div style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;background:rgba(124,58,237,.14);border:1px solid rgba(124,58,237,.35);border-radius:10px;font-weight:600;color:#fff;">
                  <?= icon('card', 18) ?> PayPal
                </div>
              </div>
            <?php elseif ($stripeEnabled): ?>
              <!-- Stripe only enabled -->
              <div class="field full">
                <label>Payment method</label>
                <input type="hidden" id="paymentProvider" name="provider" value="stripe">
                <div style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;background:rgba(124,58,237,.14);border:1px solid rgba(124,58,237,.35);border-radius:10px;font-weight:600;color:#fff;">
                  <?= icon('card', 18) ?> Stripe
                </div>
              </div>
            <?php endif; ?>

            <?php if ($paypalEnabled): ?>
              <!-- PayPal SDK slot -->
              <div class="field full payment-sdk-slot" id="paypal-payment-section" data-payment-provider="paypal" aria-label="PayPal SDK container">
                <h3>PayPal</h3>
                <?php if ($paypalSdkCode !== ''): ?>
                  <div class="payment-sdk-custom">
                    <?= $paypalSdkCode ?>
                  </div>
                <?php else: ?>
                  <div id="paypal-button-container" class="payment-sdk-container"></div>
                  <p class="payment-sdk-hint">PayPal SDK placeholder — add your own client-side SDK code in Admin &rarr; Payments.</p>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($stripeEnabled): ?>
              <!-- Stripe SDK slot -->
              <div class="field full payment-sdk-slot" id="stripe-payment-section" data-payment-provider="stripe" aria-label="Stripe SDK container" <?= ($paypalEnabled && $stripeEnabled) ? 'hidden' : '' ?>>
                <h3>Stripe</h3>
                <?php if ($stripeSdkCode !== ''): ?>
                  <div class="payment-sdk-custom">
                    <?= $stripeSdkCode ?>
                  </div>
                <?php else: ?>
                  <div id="stripe-payment-container" class="payment-sdk-container"></div>
                  <p class="payment-sdk-hint">Stripe SDK placeholder — add your own client-side SDK code in Admin &rarr; Payments.</p>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($anyEnabled): ?>
              <div class="field full"><button class="btn btn-primary btn-lg btn-block" type="submit">Continue securely</button></div>
            <?php endif; ?>
          </div>
          <div class="form-status" role="status" aria-live="polite"></div>
        </form>
      </div>
      <aside class="contact-side">
        <div class="info-card"><span class="info-icon"><?= icon('lock', 22) ?></span><span><strong>Provider-managed checkout</strong><p>Payment credentials and processing are handled only by the SDK code you add to the provider containers.</p></span></div>
        <div class="info-card"><span class="info-icon"><?= icon('mail', 22) ?></span><span><strong>Need help?</strong><p>Contact <a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a>.</p></span></div>
      </aside>
    </div>
  </div>
</section>
<script>
(function () {
  var form = document.getElementById('payForm');
  var provider = document.getElementById('paymentProvider');
  var status = form && form.querySelector('.form-status');

  function updateProvider() {
    if (!provider) return;
    var current = provider.value;
    document.querySelectorAll('[data-payment-provider]').forEach(function (slot) {
      slot.hidden = slot.getAttribute('data-payment-provider') !== current;
    });
  }

  if (provider && provider.tagName === 'SELECT') {
    provider.addEventListener('change', updateProvider);
    updateProvider();
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;
      var currentProvider = provider ? provider.value : 'payment';
      var detail = Object.fromEntries(new FormData(form).entries());
      window.dispatchEvent(new CustomEvent('tpt:payment-ready', { detail: detail }));
      if (status) {
        status.className = 'form-status show';
        status.textContent = 'Your details are ready for the ' + (currentProvider === 'paypal' ? 'PayPal' : 'Stripe') + ' SDK. Complete checkout through your configured provider container.';
      }
    });
  }
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/** Customer-owned SDK payment page. The site intentionally performs no gateway
 * requests, captures, verification, or payment persistence. */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'Pay Online — The Pie Technologies';
$metaDesc = 'Enter your payment details and continue using your chosen payment provider.';
$activeNav = 'pay';
$payServices = piePaymentServices();
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
        <form id="payForm" class="payment-form" data-payment-form novalidate>
          <div class="form-grid">
            <div class="field"><label for="pName">Full name <span class="req">*</span></label><input id="pName" name="name" type="text" required maxlength="150" autocomplete="name"></div>
            <div class="field"><label for="pEmail">Email address <span class="req">*</span></label><input id="pEmail" name="email" type="email" required maxlength="150" autocomplete="email"></div>
            <div class="field"><label for="pPhone">Phone number</label><input id="pPhone" name="phone" type="tel" maxlength="30" autocomplete="tel"></div>
            <div class="field"><label for="pService">Service <span class="req">*</span></label>
              <select id="pService" name="service" required><option value="">Select a service</option><?php foreach ($payServices as $service): ?><option value="<?= esc($service) ?>"><?= esc($service) ?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label for="pAmount">Amount (USD) <span class="req">*</span></label><input id="pAmount" name="amount" type="number" min="0.01" step="0.01" required inputmode="decimal" placeholder="0.00"></div>
            <div class="field full"><label class="pay-terms"><input type="checkbox" name="terms" value="1" required><span>I agree to the <a href="<?= url('terms') ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>. <span class="req">*</span></span></label></div>
            <div class="field full"><label for="paymentProvider">Payment method <span class="req">*</span></label>
              <select id="paymentProvider" name="provider" required><option value="paypal">PayPal</option><option value="stripe">Stripe</option></select>
            </div>
            <div class="field full payment-sdk-slot" id="paypal-payment-section" data-payment-provider="paypal" aria-label="PayPal SDK container">
              <h3>PayPal</h3><div id="paypal-button-container" class="payment-sdk-container"></div>
              <p class="payment-sdk-hint">PayPal SDK placeholder — add your own client-side SDK code here.</p>
            </div>
            <div class="field full payment-sdk-slot" id="stripe-payment-section" data-payment-provider="stripe" aria-label="Stripe SDK container" hidden>
              <h3>Stripe</h3><div id="stripe-payment-container" class="payment-sdk-container"></div>
              <p class="payment-sdk-hint">Stripe SDK placeholder — add your own client-side SDK code here.</p>
            </div>
            <div class="field full"><button class="btn btn-primary btn-lg btn-block" type="submit">Continue securely</button></div>
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
    document.querySelectorAll('[data-payment-provider]').forEach(function (slot) {
      slot.hidden = slot.getAttribute('data-payment-provider') !== provider.value;
    });
  }
  if (provider) { provider.addEventListener('change', updateProvider); updateProvider(); }
  if (form) form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!form.reportValidity()) return;
    var detail = Object.fromEntries(new FormData(form).entries());
    window.dispatchEvent(new CustomEvent('tpt:payment-ready', { detail: detail }));
    if (status) {
      status.className = 'form-status show';
      status.textContent = 'Your details are ready for the ' + (provider.value === 'paypal' ? 'PayPal' : 'Stripe') + ' SDK. Add your SDK code to the matching provider container to complete checkout.';
    }
  });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/** Pay Online — hosted PayPal and Stripe checkout with server-side confirmation. */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'Pay Online — The Pie Technologies';
$metaDesc = 'Pay securely in USD through PayPal or Stripe hosted checkout.';
$activeNav = 'pay';

$paypalReady = piePayPalConfigured();
$stripeReady = pieStripeConfigured();
$termsUrl = pieTermsUrl();
$supportEmail = getSetting('site_email', 'info@thepietechnologies.com');
$supportPhone = getSetting('site_phone', '');
$paymentServices = piePaymentServices();
$paymentReturn = PaymentGateway::processReturn($_GET);
$paymentProviders = array();
if ($paypalReady) { $paymentProviders[] = 'PayPal'; }
if ($stripeReady) { $paymentProviders[] = 'Stripe'; }

$paymentFaq = array(
    array('q' => 'Which payment methods can I use?', 'a' => 'Use PayPal or Stripe when shown in the checkout. Each button opens that provider’s secure hosted checkout, where you enter payment details directly with the provider.'),
    array('q' => 'Which currency are payments taken in?', 'a' => 'Payments are taken in US dollars (USD). The amount you enter is the amount charged.'),
    array('q' => 'Is my payment secure?', 'a' => 'Yes. Payment details are entered on PayPal or Stripe’s secure checkout. This website does not receive or store your card number or provider login.'),
    array('q' => 'How do I know my payment went through?', 'a' => 'After checkout, this page verifies the transaction directly with the payment provider and saves a payment record before showing the confirmation popup. Keep the reference for your records.'),
);

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero pay-hero">
  <div class="container">
    <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Pay Online</p>
    <h1>Pay online, securely.</h1>
    <p class="lead">A straightforward checkout through PayPal or Stripe.</p>
    <ul class="pay-badges" aria-label="Payment facts">
      <?php foreach ($paymentProviders as $providerName): ?>
      <li><?= icon('check', 14) ?> <?= esc($providerName) ?></li>
      <?php endforeach; ?>
      <li><?= icon('check', 14) ?> USD</li>
      <li><?= icon('lock', 14) ?> Hosted secure checkout</li>
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
          <p class="sub">Choose your service and amount. You’ll enter payment details on the provider’s secure hosted checkout.</p>
        </div>
        <span class="pay-secure-mark"><?= icon('lock', 18) ?> Provider-verified payment</span>
      </div>

      <div class="pay-confirmation" id="tpt-payment-confirmation" role="status" aria-live="polite" hidden></div>

      <?php if ($paypalReady || $stripeReady): ?>
      <form id="payment-form" class="pay-layout" method="post" action="<?= esc(url('pay-online')) ?>" autocomplete="off" novalidate
            data-paypal-endpoint="<?= esc(url('api/payments/paypal/create')) ?>"
            data-stripe-endpoint="<?= esc(url('api/payments/stripe/create')) ?>">
        <?= csrfField() ?>
        <div class="pay-form-fields">
          <div class="pay-field pay-field-wide">
            <label for="payment-name">Name / Business name <span aria-hidden="true">*</span></label>
            <input id="payment-name" name="name" type="text" maxlength="150" autocomplete="name" placeholder="Your name or business name" required>
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
          </div>
          <p class="pay-terms-inline">By continuing with your payment, you agree to our <a href="<?= esc($termsUrl) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</p>
        </div>

        <aside class="pay-checkout-options" aria-label="Choose a payment method">
          <div class="pay-checkout-copy">
            <p class="eyebrow">Payment method</p>
            <h3>Continue securely</h3>
            <p>Your payment is completed on the provider’s hosted checkout. Card and account details never pass through this form.</p>
          </div>
          <div class="pay-gateway-actions">
            <?php if ($paypalReady): ?>
            <button class="pay-provider-button pay-provider-paypal" type="submit" data-payment-provider="paypal">
              <span>Continue with PayPal</span><?= icon('arrow-r', 17) ?>
            </button>
            <?php endif; ?>
            <?php if ($stripeReady): ?>
            <button class="pay-provider-button pay-provider-stripe" type="submit" data-payment-provider="stripe">
              <span>Pay by card with Stripe</span><?= icon('arrow-r', 17) ?>
            </button>
            <?php endif; ?>
          </div>
          <p class="pay-err" id="paymentStatus" role="status" aria-live="polite"></p>
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
        <p>If a payment does not go through or something looks wrong, stop before paying again and talk to us first. Quote the amount and any provider reference shown, and we will check the payment.</p>
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

<div class="pay-overlay" id="tpt-modal" aria-hidden="true" hidden>
  <div class="pay-modal" role="dialog" aria-modal="true" aria-labelledby="mTitle">
    <div class="pay-tick" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></div>
    <h2 id="mTitle">Payment successful</h2>
    <p class="pay-modal-sub">Thank you. Your payment has been confirmed by the provider.</p>
    <dl>
      <dt>Amount</dt><dd id="mAmount"></dd>
      <dt>Service</dt><dd id="mService"></dd>
      <dt>Provider</dt><dd id="mProvider"></dd>
      <dt>Reference</dt><dd id="mRef"></dd>
    </dl>
    <button class="pay-modal-close" type="button" id="mClose">Close</button>
  </div>
</div>

<script>
window.TPT_PAYMENT_RETURN = <?= json_encode($paymentReturn, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
(function(){
  'use strict';
  var form = document.getElementById('payment-form');
  var result = window.TPT_PAYMENT_RETURN || {status:'none'};
  var overlay = document.getElementById('tpt-modal');
  var statusBox = document.getElementById('paymentStatus');
  var closeButton = document.getElementById('mClose');
  var originalFocus = null;
  var storageKey = 'tpt-payment-pending';
  if (!form || !overlay || !closeButton) return;

  var fields = {
    name: document.getElementById('payment-name'),
    service: document.getElementById('payment-service'),
    amount: document.getElementById('payment-amount')
  };
  var buttons = Array.prototype.slice.call(form.querySelectorAll('[data-payment-provider]'));
  buttons.forEach(function(button){ button._tptOriginalHtml = button.innerHTML; });

  function setStatus(message, kind){
    if (!statusBox) return;
    statusBox.className = 'pay-err' + (kind ? ' ' + kind : '');
    statusBox.textContent = message || '';
  }
  function clearFieldError(key){
    var field = fields[key];
    if (!field) return;
    field.removeAttribute('aria-invalid');
    var holder = field.closest('.pay-field');
    var message = holder ? holder.querySelector('.pay-field-error') : null;
    if (message) message.parentNode.removeChild(message);
  }
  function clearErrors(){
    Object.keys(fields).forEach(clearFieldError);
    setStatus('', '');
  }
  function showErrors(errors){
    Object.keys(errors || {}).forEach(function(key){
      var field = fields[key];
      if (!field) return;
      field.setAttribute('aria-invalid', 'true');
      var holder = field.closest('.pay-field');
      if (!holder) return;
      var message = holder.querySelector('.pay-field-error');
      if (!message) {
        message = document.createElement('p');
        message.className = 'pay-field-error';
        holder.appendChild(message);
      }
      message.textContent = errors[key];
    });
  }
  Object.keys(fields).forEach(function(key){
    if (!fields[key]) return;
    ['input','change'].forEach(function(type){ fields[key].addEventListener(type, function(){ clearFieldError(key); }); });
  });
  function validate(){
    clearErrors();
    var errors = {};
    var name = String(fields.name.value || '').trim();
    var service = String(fields.service.value || '').trim();
    var amount = String(fields.amount.value || '').trim();
    if (name.length < 2 || name.length > 150) errors.name = 'Please enter a valid name or business name.';
    if (!service) errors.service = 'Please choose a service.';
    if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(amount) || Number(amount) < 0.01 || Number(amount) > 1000000) {
      errors.amount = 'Please enter a valid amount in USD (0.01 to 1,000,000.00).';
    }
    if (Object.keys(errors).length) {
      showErrors(errors);
      setStatus(Object.keys(errors).map(function(key){return errors[key];}).join(' '), 'is-error');
      var first = fields[Object.keys(errors)[0]];
      if (first) first.focus();
      return false;
    }
    return true;
  }
  function setBusy(button, busy){
    form.setAttribute('aria-busy', busy ? 'true' : 'false');
    buttons.forEach(function(item){
      item.disabled = !!busy;
      if (busy && item === button) item.textContent = 'Opening secure checkout…';
      else item.innerHTML = item._tptOriginalHtml;
    });
  }
  function savePending(paymentId){
    try {
      sessionStorage.setItem(storageKey, JSON.stringify({
        payment_id: paymentId,
        name: fields.name.value,
        service: fields.service.value,
        amount: fields.amount.value
      }));
    } catch (error) { /* Browser storage may be unavailable; payment remains safe. */ }
  }
  function restorePending(){
    var pending = null;
    try { pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null'); } catch (error) { pending = null; }
    if (!pending || typeof pending !== 'object') return;
    if (result.payment_id && pending.payment_id && result.payment_id !== pending.payment_id) return;
    if (fields.name && !fields.name.value) fields.name.value = String(pending.name || '');
    if (fields.service && !fields.service.value) fields.service.value = String(pending.service || '');
    if (fields.amount && !fields.amount.value) fields.amount.value = String(pending.amount || '');
  }
  function clearPending(){
    try { sessionStorage.removeItem(storageKey); } catch (error) { /* ignore */ }
  }
  function resetPaymentForm(){
    form.reset();
    clearErrors();
    form.removeAttribute('aria-busy');
    buttons.forEach(function(button){ button.disabled = false; button.innerHTML = button._tptOriginalHtml; });
    var hiddenErrors = form.querySelectorAll('.pay-field-error');
    Array.prototype.forEach.call(hiddenErrors, function(message){ if (message.parentNode) message.parentNode.removeChild(message); });
  }
  function closeModal(){
    overlay.classList.remove('open');
    overlay.hidden = true;
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('payment-modal-open');
    if (originalFocus && originalFocus.focus && originalFocus.isConnected) {
      try { originalFocus.focus({preventScroll:true}); } catch (error) { originalFocus.focus(); }
    }
  }
  function openModal(payment){
    var amount = Number(payment.amount);
    document.getElementById('mAmount').textContent = '$' + (isFinite(amount) ? amount.toFixed(2) : '0.00') + ' USD';
    document.getElementById('mService').textContent = String(payment.service || '—');
    document.getElementById('mProvider').textContent = String(payment.provider || '');
    document.getElementById('mRef').textContent = String(payment.reference || '');
    originalFocus = document.activeElement;
    overlay.hidden = false;
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('payment-modal-open');
    closeButton.focus();
  }
  function cleanReturnUrl(){
    if (!window.history || !window.history.replaceState) return;
    try {
      var clean = new URL(window.location.href);
      ['payment_return','payment_cancelled','payment_id','state','token','session_id','PayerID'].forEach(function(key){ clean.searchParams.delete(key); });
      window.history.replaceState({}, document.title, clean.pathname + clean.search + clean.hash);
    } catch (error) { /* leave a valid provider return URL in place */ }
  }

  buttons.forEach(function(button){
    button.addEventListener('click', function(){ button.dataset.clicked = '1'; });
  });
  form.addEventListener('submit', function(event){
    event.preventDefault();
    var button = event.submitter || buttons.filter(function(item){return item.dataset.clicked === '1';})[0] || buttons[0];
    buttons.forEach(function(item){ delete item.dataset.clicked; });
    if (!button || !validate()) return;
    var provider = button.getAttribute('data-payment-provider');
    var endpoint = form.getAttribute('data-' + provider + '-endpoint');
    if (!endpoint) { setStatus('This payment method is unavailable. Please contact us.', 'is-error'); return; }
    setStatus('', '');
    setBusy(button, true);
    var data = new FormData(form);
    fetch(endpoint, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: {'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
    }).then(function(response){
      return response.text().then(function(body){
        var json;
        try { json = JSON.parse(body); } catch (error) { throw new Error('The payment service returned an invalid response.'); }
        return {ok:response.ok,json:json};
      });
    }).then(function(response){
      var json = response.json || {};
      if (!response.ok || !json.success) {
        showErrors(json.errors || {});
        setBusy(null, false);
        setStatus(json.message || 'We could not start checkout. Your form details have been kept.', 'is-error');
        return;
      }
      var checkoutUrl = new URL(String(json.redirect_url || ''), window.location.href);
      var host = checkoutUrl.hostname.toLowerCase();
      var safeHost = provider === 'paypal'
        ? ['www.paypal.com','www.sandbox.paypal.com','sandbox.paypal.com'].indexOf(host) !== -1
        : host === 'checkout.stripe.com';
      if (checkoutUrl.protocol !== 'https:' || !safeHost) {
        setBusy(null, false);
        setStatus('We could not verify the secure checkout link. Please contact us.', 'is-error');
        return;
      }
      savePending(json.payment_id || '');
      window.location.assign(checkoutUrl.href);
    }).catch(function(error){
      setBusy(null, false);
      setStatus(error && error.message ? error.message : 'Network error — your form details have been kept. Please try again.', 'is-error');
    });
  });

  closeButton.addEventListener('click', closeModal);
  overlay.addEventListener('click', function(event){ if (event.target === overlay) closeModal(); });
  document.addEventListener('keydown', function(event){
    if (!overlay.classList.contains('open')) return;
    if (event.key === 'Escape') { event.preventDefault(); closeModal(); }
    if (event.key === 'Tab') { event.preventDefault(); closeButton.focus(); }
  });

  if (result.status === 'confirmed') {
    clearPending();
    resetPaymentForm();
    openModal(result);
    cleanReturnUrl();
  } else {
    restorePending();
    if (result.status === 'cancelled') setStatus(result.message || 'Checkout was cancelled. Your form details have been kept.', 'is-cancelled');
    else if (result.status === 'processing') setStatus(result.message || 'Payment confirmation is still processing. Your form details have been kept.', 'is-cancelled');
    else if (result.status === 'error') setStatus(result.message || 'We could not confirm the payment. Your form details have been kept.', 'is-error');
    if (result.status && result.status !== 'none') cleanReturnUrl();
  }
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

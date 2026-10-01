<?php
/** Pay Online — PayPal only.
 *
 * The page renders the existing Services form and loads the PayPal JavaScript
 * SDK with the Client ID saved in the application settings (Admin → Payment
 * Settings). The order is created and approved in the browser by PayPal; there
 * is no secret, no server-side capture/confirmation, no webhook and no payment
 * record stored by this website.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'Pay Online — The Pie Technologies';
$metaDesc = 'Pay securely by PayPal in USD. Choose a service, enter the amount and pay on PayPal’s secure checkout.';
$activeNav = 'pay';

$paypalClientId = piePayPalClientId();
$paypalReady = piePayPalClientIdConfigured();
$termsUrl = pieTermsUrl();
$supportEmail = getSetting('site_email', 'info@thepietechnologies.com');
$supportPhone = getSetting('site_phone', '');
$paymentServices = piePaymentServices();

$paymentFaq = array(
    array('q' => 'Which payment method can I use?', 'a' => 'PayPal. The PayPal button below opens PayPal’s secure checkout, where you can pay with your PayPal balance, a linked bank account, or a debit or credit card.'),
    array('q' => 'Which currency are payments taken in?', 'a' => 'Payments are taken in US dollars (USD). The amount you enter is the amount charged.'),
    array('q' => 'Is my payment secure?', 'a' => 'Yes. You pay on PayPal’s own secure checkout. Your PayPal login and card details are never entered on, or stored by, this website.'),
    array('q' => 'How do I know my payment went through?', 'a' => 'PayPal shows its own payment confirmation, and this page then displays a success message with the amount, service and PayPal reference. Keep the reference for your records.'),
);

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero pay-hero">
  <div class="container">
    <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Pay Online</p>
    <h1>Pay online, securely.</h1>
    <p class="lead">A straightforward checkout through PayPal.</p>
    <ul class="pay-badges" aria-label="Payment facts">
      <li><?= icon('check', 14) ?> PayPal</li>
      <li><?= icon('check', 14) ?> USD</li>
      <li><?= icon('lock', 14) ?> Secure PayPal checkout</li>
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
          <p class="sub">Enter your details and the amount, then pay with the PayPal button. Your PayPal login and card details stay on PayPal’s secure checkout.</p>
        </div>
        <span class="pay-secure-mark"><?= icon('lock', 18) ?> Protected checkout</span>
      </div>

      <div class="pay-confirmation" id="tpt-payment-confirmation" role="status" aria-live="polite" hidden></div>

      <?php if ($paypalReady): ?>
      <form id="payment-form" class="pay-layout">
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
            <small>Enter an amount between $0.01 and $1,000,000.00.</small>
          </div>
          <p class="pay-terms-inline">By continuing with your payment, you agree to our <a href="<?= esc($termsUrl) ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</p>
        </div>

        <aside class="pay-checkout-options" aria-label="Pay with PayPal">
          <div class="pay-checkout-copy">
            <p class="eyebrow">Payment method</p>
            <h3>Pay with PayPal</h3>
            <p>Enter your details first, then use the PayPal button to complete the payment on PayPal’s secure checkout.</p>
          </div>
          <div class="pay-gateway-actions">
            <div id="paypal-button-container" class="pay-paypal-buttons"></div>
          </div>
          <p class="pay-err" data-err="pp" role="alert"></p>
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
        <p>If a payment does not go through or something looks wrong, stop before paying again and talk to us first. Quote the amount and any PayPal reference shown, and we will check the payment with PayPal.</p>
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

<div class="pay-overlay" id="tpt-modal" aria-hidden="true">
  <div class="pay-modal" role="dialog" aria-modal="true" aria-labelledby="mTitle">
    <div class="pay-tick" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></div>
    <h2 id="mTitle">Payment successful</h2>
    <p class="pay-modal-sub">Thank you. Your payment has been confirmed.</p>
    <dl>
      <dt>Amount</dt><dd id="mAmount"></dd>
      <dt>Service</dt><dd id="mService"></dd>
      <dt>Provider</dt><dd id="mProvider"></dd>
      <dt>Reference</dt><dd id="mRef"></dd>
    </dl>
    <button class="pay-modal-close" type="button" id="mClose">Close</button>
  </div>
</div>

<?php if ($paypalReady): ?>
<script src="<?= esc(piePayPalSdkUrl($paypalClientId)) ?>"></script>
<script>
/* Always convert objects to plain text so "[object Object]" can never appear. */
function tptLabel(v){
  if (v == null) return '';
  if (typeof v === 'string' || typeof v === 'number') return String(v);
  return String(v.name || v.title || v.label || v.description || '');
}
var shown = {}, lastFocus = null;
var overlay = document.getElementById('tpt-modal');

/* p = {provider, amount, service, reference} from PayPal's approved order */
function showConfirmed(p){
  if (shown[p.reference]) return;
  shown[p.reference] = true;
  var amount = '$' + Number(p.amount).toFixed(2) + ' USD';
  var service = tptLabel(p.service);
  var box = document.getElementById('tpt-payment-confirmation');
  box.textContent = 'Payment of ' + amount + (service ? ' for ' + service : '') + ' is complete. Reference: ' + p.reference + '.';
  box.hidden = false;
  document.getElementById('mAmount').textContent = amount;
  document.getElementById('mService').textContent = service || '-';
  document.getElementById('mProvider').textContent = 'PayPal';
  document.getElementById('mRef').textContent = p.reference;
  lastFocus = document.activeElement;
  overlay.classList.add('open');
  overlay.setAttribute('aria-hidden', 'false');
  document.getElementById('mClose').focus();
}
function closeModal(){
  overlay.classList.remove('open');
  overlay.setAttribute('aria-hidden', 'true');
  if (lastFocus) lastFocus.focus();
}
document.getElementById('mClose').onclick = closeModal;
overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(); });
document.addEventListener('keydown', function(e){
  if (!overlay.classList.contains('open')) return;
  if (e.key === 'Escape') closeModal();
  if (e.key === 'Tab'){ e.preventDefault(); document.getElementById('mClose').focus(); }
});

/* PayPal JavaScript SDK — the Client ID above comes from Admin → Payment Settings. */
(function(){
  var container = document.getElementById('paypal-button-container');
  var errBox = document.querySelector('[data-err="pp"]');
  if (!container || !errBox) { return; }

  function value(id){ var el = document.getElementById(id); return el ? String(el.value).trim() : ''; }
  function fail(message){ errBox.textContent = message || ''; }

  if (typeof paypal === 'undefined' || !paypal.Buttons) {
    fail('PayPal could not be loaded. Refresh the page, or contact us before paying.');
    return;
  }
  function amountValue(raw){
    if (!/^\d{1,7}(\.\d{1,2})?$/.test(raw)) return '';
    var amount = parseFloat(raw);
    if (!isFinite(amount) || amount < 0.01 || amount > 1000000) return '';
    return amount.toFixed(2);
  }
  function details(){
    var name = value('payment-name');
    var service = value('payment-service');
    var amount = amountValue(value('payment-amount'));
    if (!name) { fail('Enter your name or business name.'); return null; }
    if (!service) { fail('Choose a service from the list.'); return null; }
    if (!amount) { fail('Enter an amount from $0.01 to $1,000,000.00 USD.'); return null; }
    fail('');
    return { name: name, service: service, amount: amount };
  }

  var pending = null;
  paypal.Buttons({
    style: { layout: 'vertical', shape: 'rect', label: 'paypal', height: 48 },
    createOrder: function(data, actions){
      pending = details();
      if (!pending) { return actions.reject(); }
      return actions.order.create({
        purchase_units: [{
          description: (pending.service + ' — ' + pending.name).slice(0, 127),
          amount: { currency_code: 'USD', value: pending.amount }
        }],
        application_context: { shipping_preference: 'NO_SHIPPING', user_action: 'PAY_NOW' }
      });
    },
    onApprove: function(data, actions){
      return actions.order.capture().then(function(capture){
        var purchase = (capture && capture.purchase_units && capture.purchase_units[0]) || {};
        var captures = (purchase.payments && purchase.payments.captures) || [];
        var amount = (captures[0] && captures[0].amount && captures[0].amount.value) || (pending ? pending.amount : '');
        showConfirmed({
          provider: 'paypal',
          amount: amount,
          service: pending ? pending.service : '',
          reference: (captures[0] && captures[0].id) || (data && data.orderID) || ''
        });
      });
    },
    onCancel: function(){ fail('PayPal checkout was cancelled. You can try again whenever you are ready.'); },
    onError: function(){ fail('PayPal could not complete the payment. Please try again or contact us before paying twice.'); }
  }).render('#paypal-button-container');
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

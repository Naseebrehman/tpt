<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — starter payment implementations (admin reference)
 * ---------------------------------------------------------------------------
 *  These are COMPLETE, ready-to-paste PayPal and Stripe frontend
 *  implementations shown in Admin → Payments. They are reference code for the
 *  administrator: the public Pay Online page renders ONLY the code the
 *  administrator saves, never these templates.
 *
 *  Both templates demonstrate the required wiring:
 *
 *    {{PAYPAL_CLIENT_ID}}  PayPal Client ID from Admin → Payments
 *    {{SERVICES_OPTIONS}}  services from the EXISTING Admin Services system
 *    {{TERMS_URL}}         the ONE shared Terms & Conditions URL
 *
 *  Every HTML id is prefixed (paypal- / stripe-) so both gateways can be
 *  enabled on the same page without duplicate ids or selector conflicts, and
 *  the PayPal order is created with shipping_preference: "NO_SHIPPING" because
 *  this is a digital / service payment.
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/** Complete PayPal implementation — unique `paypal-` ids, shared services, shared terms, no shipping. */
function piePayPalStarterCode()
{
    return <<<'TPT_PAYPAL_TEMPLATE'
<!-- ==========================================================================
     PayPal payment form — complete, self-contained implementation.

     Integration points (change them in Admin → Payments, never here):
       {{PAYPAL_CLIENT_ID}}  PayPal Client ID
       {{SERVICES_OPTIONS}}  Services (the existing Admin Services system)
       {{TERMS_URL}}         shared Terms & Conditions URL

     All ids are prefixed with `paypal-` so PayPal and Stripe never collide.
     Shipping is disabled: digital / service payment, no address requested.
     No Terms checkbox: one small legal line replaces it.
     ========================================================================== -->
<style>
  /* Scoped to .paypal-pay-* so nothing else on the page is affected. */
  .paypal-pay-form{display:grid;gap:18px;max-width:560px}
  .paypal-pay-terms{font-size:.8rem;line-height:1.6;color:var(--muted-2);margin:0}
  .paypal-pay-terms a{color:var(--violet-soft);text-decoration:underline}
  .paypal-pay-buttons{min-height:52px;margin-bottom:14px}
  .paypal-pay-buttons iframe{max-width:100%}
  @media (max-width:720px){.paypal-pay-form{max-width:100%}}
</style>

<form id="paypal-payment-form" class="paypal-pay-form form-grid" novalidate>
  <div class="field full">
    <label for="paypal-name">Full name <span class="req">*</span></label>
    <input id="paypal-name" name="name" type="text" maxlength="150" autocomplete="name" placeholder="John Smith" required>
    <span class="error-msg"></span>
  </div>
  <div class="field full">
    <label for="paypal-email">Email address <span class="req">*</span></label>
    <input id="paypal-email" name="email" type="email" maxlength="150" autocomplete="email" placeholder="john@example.com" required>
    <span class="error-msg"></span>
  </div>
  <div class="field full">
    <label for="paypal-service">Service <span class="req">*</span></label>
    <select id="paypal-service" name="service" data-tpt-services required>
      <option value="">Select a service</option>
      {{SERVICES_OPTIONS}}
    </select>
    <span class="error-msg"></span>
  </div>
  <div class="field full">
    <label for="paypal-amount">Amount (USD) <span class="req">*</span></label>
    <input id="paypal-amount" name="amount" type="number" min="1" step="0.01" inputmode="decimal" placeholder="500.00" required>
    <span class="error-msg"></span>
  </div>
  <div class="field full">
    <label for="paypal-notes">Project notes</label>
    <textarea id="paypal-notes" name="notes" rows="3" maxlength="1000" placeholder="Anything the team should know (optional)"></textarea>
  </div>
  <div class="field full">
    <!-- No Terms checkbox: one small line of text, as required. -->
    <p class="paypal-pay-terms">By continuing with your payment, you agree to our <a href="{{TERMS_URL}}" data-tpt-terms target="_blank" rel="noopener">Terms &amp; Conditions</a>.</p>
  </div>
  <div class="full">
    <!-- Required PayPal render container: keep this id, it is PayPal's own. -->
    <div id="paypal-button-container" class="paypal-pay-buttons"></div>
  </div>
  <div class="form-status full" id="paypal-form-status" role="status" aria-live="polite"></div>
</form>

<script src="https://www.paypal.com/sdk/js?client-id={{PAYPAL_CLIENT_ID}}&currency=USD&intent=capture"></script>
<script>
(function () {
  'use strict';
  var form = document.getElementById('paypal-payment-form');
  var statusBox = document.getElementById('paypal-form-status');
  if (!form) { return; }

  function setStatus(message, kind) {
    if (!statusBox) { return; }
    statusBox.textContent = message || '';
    statusBox.className = 'form-status full' + (message ? ' show' : '') + (kind ? ' ' + kind : '');
  }
  function fieldOf(input) {
    return input && input.closest ? input.closest('.field') : null;
  }
  function markError(input, message) {
    var field = fieldOf(input);
    if (!field) { return; }
    field.classList.add('field-error');
    var note = field.querySelector('.error-msg');
    if (note) { note.textContent = message || ''; }
  }
  function clearErrors() {
    Array.prototype.forEach.call(form.querySelectorAll('.field-error'), function (field) {
      field.classList.remove('field-error');
    });
  }
  function validate() {
    clearErrors();
    var firstInvalid = null;
    ['paypal-name', 'paypal-email', 'paypal-service', 'paypal-amount'].forEach(function (id) {
      var input = document.getElementById(id);
      if (!input) { return; }
      var value = (input.value || '').trim();
      var message = '';
      if (!value) { message = 'This field is required.'; }
      else if (id === 'paypal-email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) { message = 'Enter a valid email address.'; }
      else if (id === 'paypal-amount' && !(parseFloat(value) > 0)) { message = 'Enter an amount greater than zero.'; }
      if (message) {
        markError(input, message);
        if (!firstInvalid) { firstInvalid = input; }
      }
    });
    if (firstInvalid) {
      setStatus('Please complete the highlighted fields.', 'err');
      try { firstInvalid.focus(); } catch (focusError) { /* ignore */ }
      return false;
    }
    setStatus('', '');
    return true;
  }

  /* Enter inside a field validates instead of submitting anywhere. */
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    validate();
  });

  function amountValue() {
    var input = document.getElementById('paypal-amount');
    var value = input ? parseFloat(input.value) : 0;
    return isFinite(value) && value > 0 ? value.toFixed(2) : '0.00';
  }
  function serviceValue() {
    var select = document.getElementById('paypal-service');
    return select && select.value ? select.value : 'Service payment';
  }
  function emailValue() {
    var input = document.getElementById('paypal-email');
    return input && input.value ? input.value.trim() : '';
  }

  function renderButtons() {
    if (!window.paypal || !window.paypal.Buttons) {
      window.setTimeout(renderButtons, 300); /* SDK is still loading */
      return;
    }
    window.paypal.Buttons({
      style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'paypal', tagline: false },
      onClick: function (data, actions) {
        /* Validate our own fields first — reject keeps the PayPal popup closed. */
        return validate() ? actions.resolve() : actions.reject();
      },
      createOrder: function (data, actions) {
        return actions.order.create({
          purchase_units: [{
            amount: { value: amountValue(), currency_code: 'USD' },
            description: serviceValue(),
            custom_id: emailValue()
          }],
          /* Digital / service payment: never request a shipping address. */
          application_context: { shipping_preference: 'NO_SHIPPING', user_action: 'PAY_NOW' }
        });
      },
      onApprove: function (data, actions) {
        /* Prefer the server-side capture: with a PayPal Secret stored in
           Admin -> Payments, window.TPT_PAYPAL.capture() asks TPT's own
           endpoint to capture and verify the order with PayPal, and the page
           then shows the confirmed payment (amount + reference). The Secret
           itself never reaches this page. Without a Secret the standard
           client-side capture runs exactly as before. */
        var verify = (window.TPT_PAYPAL && window.TPT_PAYPAL.serverVerification)
          ? window.TPT_PAYPAL.capture(data.orderID, amountValue())
          : actions.order.capture();
        return verify.then(function (details) {
          if (window.TPT_PAYPAL && window.TPT_PAYPAL.serverVerification) { return; } /* the page shows the confirmation */
          var capture = details && details.purchase_units && details.purchase_units[0] && details.purchase_units[0].payments
            ? details.purchase_units[0].payments.captures[0] : null;
          var status = capture && capture.status ? capture.status : (details && details.status);
          if (String(status || '').toUpperCase() === 'COMPLETED') {
            setStatus('Payment complete. Reference: ' + ((capture && capture.id) || (data.orderID || '')) + '.', 'ok');
          } else {
            setStatus('PayPal has not completed this payment yet. Please contact us before paying again.', 'err');
          }
        }).catch(function (error) {
          setStatus(error && error.message ? error.message : 'We could not verify this payment. Please contact us before paying again.', 'err');
        });
      },
      onCancel: function () { setStatus('The payment was cancelled.', 'err'); },
      onError: function () { setStatus('PayPal could not complete the payment. Please try again.', 'err'); }
    }).render('#paypal-button-container');
  }
  renderButtons();

  window.setTimeout(function () {
    if (!window.paypal) {
      setStatus('PayPal could not be loaded. Check the PayPal Client ID in the dashboard.', 'err');
    }
  }, 6000);
})();
</script>
TPT_PAYPAL_TEMPLATE;
}

/** Complete Stripe implementation — unique `stripe-` ids, shared services, shared terms. */
function pieStripeStarterCode()
{
    return <<<'TPT_STRIPE_TEMPLATE'
<!-- ==========================================================================
     Stripe payment form — complete, self-contained implementation.

     Integration points (change them in Admin → Payments, never here):
       {{SERVICES_OPTIONS}}  Services (the existing Admin Services system)
       {{TERMS_URL}}         shared Terms & Conditions URL

     All ids are prefixed with `stripe-` so Stripe and PayPal never collide.
     Replace STRIPE_PUBLISHABLE_KEY with your own publishable key — the
     dashboard never stores a Stripe secret.
     ========================================================================== -->
<style>
  /* Scoped to .stripe-pay-* so nothing else on the page is affected. */
  .stripe-pay-form{display:grid;gap:18px;max-width:560px}
  .stripe-pay-card{padding:14px 16px;background:var(--bg-soft);border:1px solid var(--line);border-radius:12px}
  .stripe-pay-card.StripeElement--focus{border-color:var(--violet);box-shadow:0 0 0 4px rgba(124,58,237,.16)}
  .stripe-pay-card.StripeElement--invalid{border-color:var(--red)}
  .stripe-pay-terms{font-size:.8rem;line-height:1.6;color:var(--muted-2);margin:0}
  .stripe-pay-terms a{color:var(--violet-soft);text-decoration:underline}
  @media (max-width:720px){.stripe-pay-form{max-width:100%}}
</style>

<div id="stripe-payment-container" class="stripe-pay-form">
  <form id="stripe-payment-form" class="form-grid" novalidate>
    <div class="field full">
      <label for="stripe-name">Full name <span class="req">*</span></label>
      <input id="stripe-name" name="name" type="text" maxlength="150" autocomplete="name" placeholder="John Smith" required>
      <span class="error-msg"></span>
    </div>
    <div class="field full">
      <label for="stripe-email">Email address <span class="req">*</span></label>
      <input id="stripe-email" name="email" type="email" maxlength="150" autocomplete="email" placeholder="john@example.com" required>
      <span class="error-msg"></span>
    </div>
    <div class="field full">
      <label for="stripe-service">Service <span class="req">*</span></label>
      <select id="stripe-service" name="service" data-tpt-services required>
        <option value="">Select a service</option>
        {{SERVICES_OPTIONS}}
      </select>
      <span class="error-msg"></span>
    </div>
    <div class="field full">
      <label for="stripe-amount">Amount (USD) <span class="req">*</span></label>
      <input id="stripe-amount" name="amount" type="number" min="1" step="0.01" inputmode="decimal" placeholder="500.00" required>
      <span class="error-msg"></span>
    </div>
    <div class="field full">
      <label for="stripe-card-element">Card details <span class="req">*</span></label>
      <div class="stripe-pay-card" id="stripe-card-element"><!-- Stripe Elements mounts here --></div>
      <span class="error-msg" id="stripe-card-errors"></span>
    </div>
    <div class="field full">
      <!-- No Terms checkbox: one small line of text, as required. -->
      <p class="stripe-pay-terms">By continuing with your payment, you agree to our <a href="{{TERMS_URL}}" data-tpt-terms target="_blank" rel="noopener">Terms &amp; Conditions</a>.</p>
    </div>
    <div class="full">
      <button class="btn btn-primary btn-lg btn-block" type="submit" id="stripe-payment-button">Pay now</button>
    </div>
    <div class="form-status full" id="stripe-form-status" role="status" aria-live="polite"></div>
  </form>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
  'use strict';
  /* Your own Stripe PUBLISHABLE key (pk_...). Never a secret key. */
  var STRIPE_PUBLISHABLE_KEY = 'pk_test_0123456789abcdefghijklmn';

  var form = document.getElementById('stripe-payment-form');
  var statusBox = document.getElementById('stripe-form-status');
  var cardErrors = document.getElementById('stripe-card-errors');
  var payButton = document.getElementById('stripe-payment-button');
  if (!form) { return; }

  function setStatus(message, kind) {
    if (!statusBox) { return; }
    statusBox.textContent = message || '';
    statusBox.className = 'form-status full' + (message ? ' show' : '') + (kind ? ' ' + kind : '');
  }
  function fieldOf(input) {
    return input && input.closest ? input.closest('.field') : null;
  }
  function markError(input, message) {
    var field = fieldOf(input);
    if (!field) { return; }
    field.classList.add('field-error');
    var note = field.querySelector('.error-msg');
    if (note) { note.textContent = message || ''; }
  }
  function clearErrors() {
    Array.prototype.forEach.call(form.querySelectorAll('.field-error'), function (field) {
      field.classList.remove('field-error');
    });
  }
  function validate() {
    clearErrors();
    var firstInvalid = null;
    ['stripe-name', 'stripe-email', 'stripe-service', 'stripe-amount'].forEach(function (id) {
      var input = document.getElementById(id);
      if (!input) { return; }
      var value = (input.value || '').trim();
      var message = '';
      if (!value) { message = 'This field is required.'; }
      else if (id === 'stripe-email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) { message = 'Enter a valid email address.'; }
      else if (id === 'stripe-amount' && !(parseFloat(value) > 0)) { message = 'Enter an amount greater than zero.'; }
      if (message) {
        markError(input, message);
        if (!firstInvalid) { firstInvalid = input; }
      }
    });
    if (firstInvalid) {
      setStatus('Please complete the highlighted fields.', 'err');
      try { firstInvalid.focus(); } catch (focusError) { /* ignore */ }
      return false;
    }
    setStatus('', '');
    return true;
  }

  var stripe = window.Stripe ? window.Stripe(STRIPE_PUBLISHABLE_KEY) : null;
  var card = stripe ? stripe.elements().create('card', { hidePostalCode: true }) : null;
  if (card) {
    card.mount('#stripe-card-element');
    card.addEventListener('change', function (event) {
      if (cardErrors) { cardErrors.textContent = event.error ? event.error.message : ''; }
    });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!validate()) { return; }
    if (!stripe || !card) {
      setStatus('Stripe could not be loaded. Please refresh and try again.', 'err');
      return;
    }
    if (payButton) { payButton.disabled = true; }
    setStatus('Processing…', '');
    stripe.createToken(card, {
      name: (document.getElementById('stripe-name') || {}).value || '',
      email: (document.getElementById('stripe-email') || {}).value || ''
    }).then(function (result) {
      if (payButton) { payButton.disabled = false; }
      if (result.error) {
        setStatus(result.error.message, 'err');
        return;
      }
      /* Hand the token to your own processor / dashboard — this page stores nothing. */
      setStatus('Card verified. Payment token: ' + result.token.id + '.', 'ok');
    }).catch(function () {
      if (payButton) { payButton.disabled = false; }
      setStatus('Stripe could not complete the payment. Please try again.', 'err');
    });
  });
})();
</script>
TPT_STRIPE_TEMPLATE;
}

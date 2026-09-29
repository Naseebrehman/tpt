/* ===========================================================================
   THE PIE TECHNOLOGIES — payment page client (Tasks 3–5, 27)
   Two-phase flow:
     1) validate the short form + create the payment record server-side
     2) show ONE payment interface at a time:
          PayPal  → PayPal Buttons (JS SDK)
          Stripe  → Stripe Elements (Payment Element)
   Loading states, duplicate-submit prevention, professional success/failure
   panels. No alert() anywhere. No card data ever handled by this script.
   =========================================================================== */
(function () {
  'use strict';

  var form = document.querySelector('[data-pay-form]');
  if (!form) return;

  var successPanel = document.getElementById('paySuccess');
  var failurePanel = document.getElementById('payFailure');
  var continueWrap = document.getElementById('payContinueWrap');
  var paypalWrap   = document.getElementById('paypalButtonWrap');
  var stripeWrap   = document.getElementById('stripePaymentWrap');
  var stripePayBtn = document.getElementById('stripePayBtn');
  var retryBtn     = document.getElementById('payRetryBtn');
  var submitBtn    = document.getElementById('paySubmitBtn');
  var statusBox    = form.querySelector('.form-status');

  var state = { busy: false, token: '', method: '', initialized: false, stripe: null, elements: null, paymentElement: null, intentId: '' };

  /* ------------------------------ helpers ------------------------------- */
  function setStatus(message, kind) {
    if (!statusBox) return;
    statusBox.className = 'form-status' + (kind ? ' ' + kind + ' show' : '');
    statusBox.textContent = message || '';
  }
  function clearFieldErrors() {
    form.querySelectorAll('.field-msg').forEach(function (el) { el.parentNode.removeChild(el); });
    form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
  }
  function showFieldErrors(errors) {
    clearFieldErrors();
    Object.keys(errors || {}).forEach(function (name) {
      var input = form.querySelector('[name="' + name + '"]');
      if (!input) return;
      input.setAttribute('aria-invalid', 'true');
      var holder = input.closest('.field') || input.parentNode;
      var msg = document.createElement('small');
      msg.className = 'field-msg';
      msg.textContent = errors[name];
      holder.appendChild(msg);
    });
  }
  function busy(on) {
    state.busy = on;
    if (submitBtn) { submitBtn.disabled = on; submitBtn.textContent = on ? 'Processing…' : 'Continue to Payment →'; }
    if (stripePayBtn) { stripePayBtn.disabled = on; }
    form.querySelectorAll('input,select,textarea').forEach(function (el) { el.disabled = on; });
  }
  function showSuccess(data) {
    if (successPanel) {
      var set = function (id, value) { var el = document.getElementById(id); if (el) el.textContent = value || '—'; };
      set('psName', data.name);
      set('psAmount', '$' + (data.amount || '0.00') + ' USD');
      set('psService', data.service);
      set('psMethod', data.method);
      set('psTxn', data.transaction_id);
      successPanel.hidden = false;
      successPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    if (form) form.style.display = 'none';
    if (failurePanel) failurePanel.hidden = true;
  }
  function showFailure() {
    if (failurePanel) {
      failurePanel.hidden = false;
      failurePanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    if (successPanel) successPanel.hidden = true;
    if (continueWrap) continueWrap.hidden = false;
    busy(false);
    setStatus('');
  }

  function captchaToken() {
    for (var i = 0; i < form.elements.length; i++) {
      var el = form.elements[i];
      if (!el.name) continue;
      if ((el.name === 'cf-turnstile-response' || el.name === 'h-captcha-response' || el.name === 'g-recaptcha-response') && el.value) {
        return el.value;
      }
    }
    return '';
  }

  function post(extra) {
    var data = new FormData(form);
    Object.keys(extra || {}).forEach(function (key) { data.set(key, extra[key]); });
    data.set('captcha_token', captchaToken());
    return fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (res) {
      return res.json().catch(function () { return { success: false, message: 'Unexpected server response.' }; });
    }).catch(function () {
      return { success: false, message: 'Network error — check your connection and try again.' };
    });
  }

  /* --------------------------- phase 1: init ---------------------------- */
  function validateClient() {
    var errors = {};
    var name = form.elements['name'].value.trim();
    var email = form.elements['email'].value.trim();
    var phone = form.elements['phone'].value.trim();
    var service = form.elements['service'].value;
    var amount = form.elements['amount'].value.trim();
    var method = form.querySelector('input[name="method"]:checked');
    var terms = form.querySelector('[name="terms"]');
    if (name.length < 2) errors.name = 'Please enter your full name.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Please enter a valid email address.';
    if (phone.replace(/[^0-9]/g, '').length < 7) errors.phone = 'Please enter a valid phone number with country code.';
    if (!service) errors.service = 'Please choose the service you are paying for.';
    if (!/^[0-9]+(\.[0-9]{1,2})?$/.test(amount) || parseFloat(amount) <= 0) errors.amount = 'Enter a valid amount in USD (e.g. 1500.00).';
    if (!method) errors.method = 'Choose a payment method.';
    if (terms && !terms.checked) errors.terms = 'Please accept the Terms & Conditions to continue.';
    return errors;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (state.busy) return;
    clearFieldErrors();
    setStatus('');

    var errors = validateClient();
    if (Object.keys(errors).length) {
      showFieldErrors(errors);
      setStatus('Please fix the highlighted fields.', 'err');
      return;
    }

    var method = form.querySelector('input[name="method"]:checked').value;
    if (method === 'invoice' || form.getAttribute('data-invoice-fallback') === '1') {
      /* Invoice request path (kept for setups without providers). */
      busy(true);
      setStatus('Submitting your request…');
      var data = new FormData(form);
      data.set('pay_submit', '1');
      fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST', body: data,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (r) { return r.json(); }).then(function (json) {
        busy(false);
        if (json && json.success) {
          if (json.redirect) { window.location.href = json.redirect; return; }
          setStatus('Request received — check your email for the secure payment link.', 'ok');
          form.style.display = 'none';
          return;
        }
        setStatus((json && json.message) || 'Could not submit the request.', 'err');
        if (json && json.errors) showFieldErrors(json.errors);
      }).catch(function () {
        busy(false);
        setStatus('Network error — please try again.', 'err');
      });
      return;
    }

    busy(true);
    setStatus('Preparing your payment…');
    post({ payment_action: 'init', method: method }).then(function (json) {
      if (json && json.success && json.token) {
        state.token = json.token;
        state.method = method;
        state.initialized = true;
        setStatus('');
        if (method === 'paypal') {
          startPayPal();
        } else {
          startStripe();
        }
        return;
      }
      if (json && json.success && !json.token) {
        /* honeypot silently accepted */
        busy(false);
        setStatus('Thanks — we received your request.', 'ok');
        form.style.display = 'none';
        return;
      }
      busy(false);
      setStatus((json && json.message) || 'Please check the form and try again.', 'err');
      if (json && json.errors) showFieldErrors(json.errors);
    });
  });

  /* ------------------------ phase 2a: PayPal ---------------------------- */
  function loadPayPalSdk() {
    return new Promise(function (resolve, reject) {
      if (window.paypal && window.paypal.Buttons) { resolve(window.paypal); return; }
      var clientId = form.getAttribute('data-paypal-client-id') || '';
      if (!clientId) { reject(new Error('missing client id')); return; }
      if (document.getElementById('paypalSdk')) {
        var wait = setInterval(function () {
          if (window.paypal && window.paypal.Buttons) { clearInterval(wait); resolve(window.paypal); }
        }, 120);
        setTimeout(function () { clearInterval(wait); reject(new Error('timeout')); }, 15000);
        return;
      }
      var script = document.createElement('script');
      script.id = 'paypalSdk';
      script.src = 'https://www.paypal.com/sdk/js?client-id=' + encodeURIComponent(clientId) + '&currency=USD&intent=capture';
      script.onload = function () { resolve(window.paypal); };
      script.onerror = function () { reject(new Error('load failed')); };
      document.head.appendChild(script);
    });
  }

  function startPayPal() {
    if (paypalWrap) paypalWrap.hidden = false;
    if (continueWrap) continueWrap.hidden = true;
    busy(false);
    setStatus('Click the PayPal button to complete your payment.');
    loadPayPalSdk().then(function (paypal) {
      var buttons = paypal.Buttons({
        style: { layout: 'vertical', shape: 'rect', label: 'pay', height: 48 },
        createOrder: function (data, actions) {
          if (state.busy) return null;
          busy(true);
          setStatus('Contacting PayPal…');
          return post({ payment_action: 'paypal_create', token: state.token }).then(function (json) {
            if (json && json.success && json.order_id) return json.order_id;
            if (json && json.success && json.client_side) {
              /* No server secret configured — create the order through the SDK. */
              return actions.order.create({
                purchase_units: [{
                  description: 'TPT payment',
                  amount: { currency_code: 'USD', value: form.amount.value.trim() }
                }]
              });
            }
            busy(false);
            setStatus((json && json.message) || 'PayPal is unavailable right now.', 'err');
            return null;
          });
        },
        onApprove: function (data) {
          setStatus('Confirming your payment…');
          return post({ payment_action: 'paypal_capture', token: state.token, order_id: data.orderID }).then(function (json) {
            busy(false);
            if (json && json.success && json.status === 'paid') {
              showSuccess(json);
            } else if (json && json.success && json.status === 'pending') {
              setStatus('Payment Successful — we’ve emailed your receipt. Thank you!', 'ok');
              showSuccess(json);
            } else {
              setStatus((json && json.message) || 'Payment Could Not Be Completed — if you were charged, your receipt will arrive by email shortly.', 'err');
              showFailure();
            }
          });
        },
        onCancel: function () {
          busy(false);
          setStatus('Payment cancelled — nothing was charged. You can try again whenever you’re ready.', 'err');
        },
        onError: function () {
          busy(false);
          showFailure();
        }
      });
      if (buttons && typeof buttons.render === 'function') {
        var mount = document.getElementById('paypalButtons');
        if (mount) { mount.innerHTML = ''; buttons.render('#paypalButtons'); }
      } else {
        setStatus('PayPal could not load. Please refresh the page or use a card.', 'err');
      }
    }).catch(function () {
      setStatus('PayPal could not load. Check your connection and try again.', 'err');
    });
  }

  /* ------------------------ phase 2b: Stripe ---------------------------- */
  function loadStripeSdk() {
    return new Promise(function (resolve, reject) {
      if (window.Stripe) { resolve(window.Stripe); return; }
      if (document.getElementById('stripeSdk')) {
        var wait = setInterval(function () {
          if (window.Stripe) { clearInterval(wait); resolve(window.Stripe); }
        }, 120);
        setTimeout(function () { clearInterval(wait); reject(new Error('timeout')); }, 15000);
        return;
      }
      var script = document.createElement('script');
      script.id = 'stripeSdk';
      script.src = 'https://js.stripe.com/v3/';
      script.onload = function () { resolve(window.Stripe); };
      script.onerror = function () { reject(new Error('load failed')); };
      document.head.appendChild(script);
    });
  }

  function startStripe() {
    if (stripeWrap) stripeWrap.hidden = false;
    if (continueWrap) continueWrap.hidden = true;
    busy(true);
    setStatus('Loading the secure card form…');
    loadStripeSdk().then(function (Stripe) {
      return post({ payment_action: 'stripe_intent', token: state.token }).then(function (json) {
        if (!(json && json.success && json.client_secret)) {
          busy(false);
          setStatus((json && json.message) || 'Card payments are unavailable right now.', 'err');
          return;
        }
        if (json.already_paid) {
          busy(false);
          showSuccess(json);
          return;
        }
        state.intentId = json.intent_id || '';
        state.stripe = Stripe(json.publishable_key);
        state.elements = state.stripe.elements({ clientSecret: json.client_secret });
        state.paymentElement = state.elements.create('payment', { layout: 'tabs' });
        state.paymentElement.mount('#stripePaymentElement');
        state.paymentElement.on('ready', function () {
          busy(false);
          setStatus('Enter your card details, then click Pay Securely.');
        });
      });
    }).catch(function () {
      busy(false);
      setStatus('Stripe could not load. Check your connection and try again.', 'err');
    });
  }

  if (stripePayBtn) {
    stripePayBtn.addEventListener('click', function () {
      if (state.busy || !state.stripe || !state.elements) return;
      busy(true);
      setStatus('Processing your payment…');
      state.stripe.confirmPayment({
        elements: state.elements,
        redirect: 'if_required'
      }).then(function (result) {
        if (result.error) {
          busy(false);
          /* Card declined / validation problem — professional message, no raw errors. */
          setStatus('Payment Could Not Be Completed — please check your card details and try again.', 'err');
          return;
        }
        setStatus('Confirming with the bank…');
        post({ payment_action: 'stripe_confirm', token: state.token, intent_id: state.intentId }).then(function (json) {
          busy(false);
          if (json && json.success && json.status === 'paid') {
            showSuccess(json);
          } else if (json && json.success && json.status === 'pending') {
            setStatus('Your payment is processing — we’ll email your receipt once it settles.', 'ok');
            showSuccess(json);
          } else {
            showFailure();
          }
        });
      });
    });
  }

  if (retryBtn) {
    retryBtn.addEventListener('click', function () {
      if (failurePanel) failurePanel.hidden = true;
      if (form) form.style.display = '';
      if (paypalWrap) paypalWrap.hidden = true;
      if (stripeWrap) stripeWrap.hidden = true;
      if (continueWrap) continueWrap.hidden = false;
      state.initialized = false;
      state.token = '';
      clearFieldErrors();
      busy(false);
      setStatus('');
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }
})();

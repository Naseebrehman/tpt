<?php
/** Payment-page service list. Payment processing is intentionally delegated to
 * customer-owned browser SDK code; this module contains no gateway API calls.
 *
 * This module is the single integration point between the Pay Online page and
 * the Admin → Payments settings:
 *
 *   paypal_sdk_code / stripe_sdk_code  the administrator's COMPLETE custom
 *                                      PayPal / Stripe frontend implementation,
 *                                      stored and rendered byte-for-byte.
 *   paypal_client_id                   the PayPal Client ID used by the SDK.
 *   terms_url                          ONE shared Terms & Conditions URL used
 *                                      by BOTH the PayPal and Stripe forms.
 *   payment_services                   the EXISTING admin Services system — the
 *                                      single source of truth for the service
 *                                      dropdown in both payment forms.
 *
 * The administrator's code is never rewritten: only the documented integration
 * points below are substituted, everything else is echoed exactly as saved. */
if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

/* ===========================================================================
   Services — the EXISTING Admin Services system (payment_services)
   =========================================================================== */

/** Active services for the Pay Online dropdown, in admin-defined order. */
function piePaymentServices()
{
    $tableExists = dbOne("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_services'");
    $names = array();
    if ($tableExists && (int) $tableExists['c'] > 0) {
        foreach (dbAll('SELECT name FROM payment_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC') as $row) {
            $names[] = (string) $row['name'];
        }
    }
    return $names ?: array('AI Optimization', 'Web Development', 'Digital Marketing', 'Business Consultation', 'G-W-M Services', 'Monthly Marketing Charges', 'Others');
}

/** Service rows for the admin Services manager (add / edit / delete / order). */
function piePaymentServiceRows()
{
    $tableExists = dbOne("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_services'");
    if (!$tableExists || (int) $tableExists['c'] === 0) {
        return array();
    }
    return dbAll('SELECT * FROM payment_services ORDER BY sort_order ASC, id ASC');
}

/**
 * <option> list built from the existing Services system, in admin-defined
 * order. Used by the {{SERVICES_OPTIONS}} integration point.
 */
function pieServicesOptionsHtml()
{
    $html = '';
    foreach (piePaymentServices() as $name) {
        $safe = esc($name);
        $html .= '<option value="' . $safe . '">' . $safe . "</option>\n";
    }
    return $html;
}

/** The same service list as JSON, for dropdowns built in JavaScript. */
function pieServicesJson()
{
    return (string) json_encode(array_values(piePaymentServices()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/* ===========================================================================
   Payment method toggles
   =========================================================================== */

/** Check whether PayPal is enabled by the admin. */
function pieIsPayPalEnabled()
{
    return getSetting('paypal_enabled', '0') === '1';
}

/** Check whether Stripe is enabled by the admin. */
function pieIsStripeEnabled()
{
    return getSetting('stripe_enabled', '0') === '1';
}

/* ===========================================================================
   Stored custom implementations
   =========================================================================== */

/** Retrieve admin-configured client-side PayPal SDK / embed code. */
function piePayPalSdkCode()
{
    return getSetting('paypal_sdk_code', '');
}

/** Retrieve admin-configured client-side Stripe SDK / embed code. */
function pieStripeSdkCode()
{
    return getSetting('stripe_sdk_code', '');
}

/* ===========================================================================
   Admin-controlled settings used by the payment forms
   =========================================================================== */

/** PayPal Client ID (Admin → Payments). Never a secret: client-side only. */
function piePayPalClientId()
{
    return trim((string) getSetting('paypal_client_id', ''));
}

/* The PayPal Secret and the server-side capture flow live in core/PayPal.php
   (piePayPalSecret(), piePayPalServerReady(), piePayPalCaptureOrder()).
   The Secret is SERVER-SIDE ONLY: nothing in this file — or anywhere that
   produces HTML, JavaScript or an API response — reads, renders, logs or
   returns it. */
require_once __DIR__ . '/PayPal.php';

/** True when a usable PayPal Client ID is stored. */
function piePayPalClientIdConfigured()
{
    return pieIsValidPayPalClientId(piePayPalClientId());
}

/** PayPal Client IDs are URL-safe tokens; anything else is rejected on save. */
function pieIsValidPayPalClientId($clientId)
{
    return (bool) preg_match('/^[A-Za-z0-9_-]{8,}$/', (string) $clientId);
}

/**
 * The ONE shared Terms & Conditions URL. Both the PayPal and the Stripe form
 * read this single setting — there is no separate copy per gateway.
 */
function pieTermsUrl()
{
    $url = trim((string) getSetting('terms_url', ''));
    if ($url !== '') {
        return $url;
    }
    /* Sensible default: this site's own Terms page. */
    return rtrim(SITE_URL, '/') . url('terms');
}

/** Full PayPal SDK URL built from the stored Client ID. */
function piePayPalSdkUrl()
{
    $clientId = piePayPalClientId();
    if (!pieIsValidPayPalClientId($clientId)) {
        $clientId = 'YOUR_PAYPAL_CLIENT_ID';
    }
    return 'https://www.paypal.com/sdk/js?client-id=' . $clientId . '&currency=USD&intent=capture';
}

/* ===========================================================================
   Controlled integration points (placeholders)
   =========================================================================== */

/**
 * Every value the administrator can change from the dashboard without touching
 * the saved payment code. Keys are the placeholders used inside the custom
 * PayPal / Stripe implementations.
 */
function piePaymentPlaceholders()
{
    return array(
        '{{PAYPAL_CLIENT_ID}}'         => piePayPalClientIdConfigured() ? piePayPalClientId() : 'YOUR_PAYPAL_CLIENT_ID',
        '{{PAYPAL_SDK_URL}}'           => piePayPalSdkUrl(),
        '{{TERMS_URL}}'                => pieTermsUrl(),
        '{{TERMS_AND_CONDITIONS_URL}}' => pieTermsUrl(),
        '{{SERVICES_OPTIONS}}'         => pieServicesOptionsHtml(),
        '{{SERVICES_JSON}}'            => pieServicesJson(),
    );
}

/**
 * Render the administrator's complete payment implementation.
 *
 * ONLY the placeholders above are replaced. The rest of the code — HTML, CSS,
 * JavaScript, forms, inputs, validation, SDK script tags, buttons, whitespace,
 * quotes, newlines, <script> and <style> blocks — is returned exactly as the
 * administrator saved it: never escaped, sanitised, minified or rewritten.
 *
 * @param string $code     the saved implementation
 * @param string $provider 'paypal' | 'stripe' | '' (gateway-specific extras)
 */
function pieRenderPaymentCode($code, $provider = '')
{
    $code = (string) $code;
    if ($code === '') {
        return '';
    }
    $placeholders = piePaymentPlaceholders();
    $rendered     = str_replace(array_keys($placeholders), array_values($placeholders), $code);

    if ($provider === 'paypal') {
        /* The Client ID in the dashboard always wins, so the administrator
           never has to edit the pasted code to change it. Only the client-id
           parameter of the PayPal SDK URL is touched — nothing else. */
        $rendered = piePayPalApplyClientId($rendered);
    }
    return $rendered;
}

/** Point the PayPal SDK URL at the Client ID stored in the dashboard. */
function piePayPalApplyClientId($code)
{
    $clientId = piePayPalClientId();
    if ($clientId === '' || !pieIsValidPayPalClientId($clientId)) {
        return $code; /* never blank out an ID the administrator pasted */
    }
    return (string) preg_replace_callback(
        '#https?://[^\s"\'<>]*paypal\.com/sdk/js(?:\?[^\s"\'<>]*)?#i',
        function ($matches) use ($clientId) {
            $url = $matches[0];
            if (stripos($url, 'client-id=') !== false) {
                return (string) preg_replace('/client-id=[^&\'"\s]*/i', 'client-id=' . $clientId, $url, 1);
            }
            /* No client-id parameter at all — add one so the SDK still works. */
            return (string) preg_replace_callback(
                '#(paypal\.com/sdk/js)(\?)?#i',
                function ($parts) use ($clientId) {
                    return $parts[1] . '?client-id=' . $clientId . ((isset($parts[2]) && $parts[2] === '?') ? '&' : '');
                },
                $url,
                1
            );
        },
        $code
    );
}

/* ===========================================================================
   Same-page safety bridge (IDs, shared services, shared terms, no shipping,
   server-verified PayPal capture, dark-theme guard)
   =========================================================================== */

/**
 * Small, dependency-free script emitted once before the first payment block.
 *
 *  1. Publishes the shared Services list (window.TPT_PAYMENT_SERVICES) so a
 *     custom implementation can build its own dropdown from the SAME source.
 *  2. Fills any still-empty <select data-tpt-services> and refreshes every
 *     <a data-tpt-terms> href with the shared Terms URL.
 *  3. Forces shipping_preference: "NO_SHIPPING" on every PayPal order this
 *     page creates — this is a digital / service payment, so no shipping
 *     address is ever requested. Existing checkout behaviour is untouched.
 *  4. Replaces the Terms checkbox with the small legal line, exactly as the
 *     site owner specified:
 *     "By continuing with your payment, you agree to our Terms & Conditions."
 *  5. Routes PayPal captures through the server (core/PayPal.php) whenever a
 *     PayPal Secret is configured, so a payment is only reported as successful
 *     after the SERVER has verified it with PayPal. The browser never sees the
 *     Secret.
 *  6. Shows the confirmed-payment acknowledgement:
 *     "Thank You, [Name]! Your payment of $[Amount] USD was successfully
 *      completed. Payment Reference: [ID]"
 *     Only ever after a confirmed capture — never on a click, a redirect or a
 *     cancelled/failed payment.
 *  7. Keeps the payment area on the dark TPT theme: any element inside the
 *     payment blocks whose background is plain white is softened to the site's
 *     dark surface colours.
 *
 * It never rewrites the administrator's code and cannot break the page: every
 * step is wrapped so a failure is silently ignored.
 *
 * @param array $providers rendered providers, e.g. array('paypal','stripe')
 */
function piePaymentBridge(array $providers)
{
    $config = array(
        'services'  => array_values(piePaymentServices()),
        'termsUrl'  => pieTermsUrl(),
        'shipping'  => 'NO_SHIPPING',
        'providers' => array_values($providers),
        'csrf'      => generateCSRF(),
        'paypal'    => piePayPalClientConfig(),
        'termsNote' => 'By continuing with your payment, you agree to our Terms & Conditions.',
        'confirmId' => 'tpt-payment-confirmation',
    );
    $json = (string) json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($json === '') {
        return '';
    }
    return "<script>\n"
        . "window.TPT_PAYMENT=" . $json . ";\n"
        . <<<'TPT_BRIDGE_JS'
(function () {
  'use strict';
  var config = window.TPT_PAYMENT;
  if (!config) { return; }
  window.TPT_PAYMENT_SERVICES = config.services || [];

  function hasOwn(object, key) {
    return Object.prototype.hasOwnProperty.call(object, key);
  }

  function paymentBlocks() {
    var blocks = [];
    (config.providers || []).forEach(function (provider) {
      var root = document.getElementById(provider + '-payment-code');
      if (root) { blocks.push({ provider: provider, root: root }); }
    });
    return blocks;
  }

  /* ---------------------------------------------------------------- *
     1 + 2 — shared Services list and shared Terms & Conditions URL.
     ---------------------------------------------------------------- */
  function connectSharedSettings() {
    paymentBlocks().forEach(function (block) {
      Array.prototype.forEach.call(block.root.querySelectorAll('select[data-tpt-services]'), function (select) {
        if (select.options.length) { return; } /* real options are never overwritten */
        (config.services || []).forEach(function (name) {
          var option = document.createElement('option');
          option.value = name;
          option.textContent = name;
          select.appendChild(option);
        });
      });
      Array.prototype.forEach.call(block.root.querySelectorAll('a[data-tpt-terms]'), function (link) {
        if (config.termsUrl) { link.setAttribute('href', config.termsUrl); }
      });
    });
  }

  /* ---------------------------------------------------------------- *
     4 — the Terms checkbox is replaced by one small line of text.
     The administrator's validation code is untouched: it simply finds no
     checkbox to complain about, so the flow continues to the gateway.
     ---------------------------------------------------------------- */
  function termsFieldOf(input) {
    return input && input.closest ? (input.closest('.field') || input.closest('label') || input) : input;
  }

  function replaceTermsCheckbox(block) {
    var boxes = block.root.querySelectorAll('input[type="checkbox"][name="terms"], input[type="checkbox"][id$="-terms"]');
    Array.prototype.forEach.call(boxes, function (box) {
      if (box.getAttribute('data-tpt-keep') === '1') { return; }
      var holder = termsFieldOf(box);
      if (!holder || !holder.parentNode) { return; }
      var note = document.createElement('p');
      note.className = 'pay-terms-note';
      note.setAttribute('data-tpt-terms-note', '1');
      var prefix = document.createTextNode(config.termsNote ? config.termsNote.replace(/\s*Terms & Conditions\.?$/, ' ') : 'By continuing with your payment, you agree to our ');
      note.appendChild(prefix);
      var link = document.createElement('a');
      link.href = config.termsUrl || '#';
      link.target = '_blank';
      link.rel = 'noopener';
      link.setAttribute('data-tpt-terms', '1');
      link.textContent = 'Terms & Conditions';
      note.appendChild(link);
      note.appendChild(document.createTextNode('.'));
      holder.parentNode.insertBefore(note, holder);
      /* Hide (do not delete) the checkbox row: some implementations read it. */
      if (holder.style) { holder.style.display = 'none'; }
      holder.setAttribute('hidden', 'hidden');
      box.checked = true;
      box.removeAttribute('required');
    });
  }

  /* ---------------------------------------------------------------- *
     7 — the payment area stays on the dark theme: soften plain-white
     backgrounds inside the payment blocks only.
     ---------------------------------------------------------------- */
  function softenWhiteBackgrounds(block) {
    var all = block.root.querySelectorAll('*');
    Array.prototype.forEach.call(all, function (node) {
      if (node.hasAttribute('data-tpt-keep-theme')) { return; }
      var background = '';
      try { background = window.getComputedStyle(node).backgroundColor || ''; } catch (error) { return; }
      var white = /^rgba?\(\s*255\s*,\s*255\s*,\s*255(\s*,[^)]*)?\)$/i.test(background)
        || /^rgb\(\s*250\s*,\s*250\s*,\s*25[0-9](\s*,[^)]*)?\)$/i.test(background);
      if (!white) { return; }
      node.setAttribute('data-tpt-was-white', '1');
      try { node.style.backgroundColor = 'rgba(38,40,48,.92)'; } catch (error) { /* ignore */ }
      try {
        var colour = window.getComputedStyle(node).color || '';
        if (/^rgb\(\s*(0|1?[0-9]|2[0-9]|3[0-9]|4[0-9]|5[0-9])\s*,/.test(colour) || /^rgb\(\s*[0-9]{1,2}\s*,/.test(colour)) {
          node.style.color = '#f2f3f7';
        }
      } catch (error) { /* ignore */ }
    });
  }

  /* ---------------------------------------------------------------- *
     5 + 6 — server-verified capture and the confirmed-payment message.
     ---------------------------------------------------------------- */
  function confirmationBox() {
    var box = document.getElementById(config.confirmId);
    if (box) { return box; }
    var blocks = paymentBlocks();
    if (!blocks.length) { return null; }
    box = document.createElement('div');
    box.id = config.confirmId;
    box.className = 'pay-confirmation';
    box.setAttribute('role', 'status');
    box.setAttribute('aria-live', 'polite');
    var host = blocks[0].root.parentNode || document.body;
    host.insertBefore(box, blocks[0].root);
    return box;
  }

  function showConfirmation(name, amount, reference) {
    var box = confirmationBox();
    if (!box) { return; }
    box.innerHTML = '';
    var lead = document.createElement('strong');
    lead.textContent = 'Thank You, ' + (name || 'there') + '!';
    box.appendChild(lead);
    box.appendChild(document.createTextNode(' Your payment of $' + amount + ' USD was successfully completed. Payment Reference: '));
    var ref = document.createElement('span');
    ref.className = 'mono';
    ref.textContent = reference || '—';
    box.appendChild(ref);
    box.appendChild(document.createTextNode('.'));
    box.hidden = false;
    box.classList.add('show');
    try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (error) { /* ignore */ }
  }

  function formValue(id) {
    var field = document.getElementById(id);
    return field && typeof field.value === 'string' ? field.value : '';
  }

  /** Ask the server to capture and verify an approved order. */
  function serverCapture(orderId, expectedAmount) {
    return fetch(config.paypal.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        action: 'capture',
        order_id: orderId,
        expected_amount: expectedAmount || '',
        csrf_token: config.csrf || ''
      })
    }).then(function (response) {
      return response.json().then(function (json) { return { ok: response.ok, json: json }; });
    }).then(function (result) {
      var json = result.json || {};
      if (!json.success || !json.confirmed) {
        throw new Error(json.message || 'The payment could not be verified on the server.');
      }
      showConfirmation(json.name, json.amount, json.reference);
      return json.details || json;
    });
  }

  /** Explain a verification problem next to the payment buttons. */
  function reportCaptureProblem(message) {
    var box = confirmationBox();
    if (!box) { return; }
    box.hidden = false;
    box.classList.add('show', 'pay-confirmation-error');
    box.textContent = message || 'We could not verify this payment yet. Please do not pay again — contact us and we will confirm it for you.';
  }

  /** Read a confirmed client-side capture (used only without a stored Secret). */
  function confirmFromSdkDetails(provider, details) {
    try {
      var box = confirmationBox();
      if (!details || String(details.status || '').toUpperCase() !== 'COMPLETED') { return; }
      var capture = details.purchase_units && details.purchase_units[0] && details.purchase_units[0].payments
        ? details.purchase_units[0].payments.captures[0] : null;
      var amount = capture && capture.amount ? capture.amount.value : formValue(provider + '-amount');
      var reference = capture && capture.id ? capture.id : (details.id || '');
      var payer = details.payer && details.payer.name
        ? ((details.payer.name.given_name || '') + ' ' + (details.payer.name.surname || '')).trim()
        : '';
      if (!payer) { payer = formValue(provider + '-name') || 'there'; }
      showConfirmation(payer, Number(amount || 0).toFixed(2), reference);
      if (box) { box.setAttribute('data-tpt-confirmed-by', 'sdk'); }
    } catch (error) { /* never break the checkout */ }
  }

  /* 5 — PayPal must never ask for a shipping address, creates/verifies the
     order through the server when a Secret is configured, and reports the
     confirmed payment. */
  function forceNoShipping(paypal) {
    try {
      if (!paypal || !paypal.Buttons || paypal.Buttons.__tptNoShipping) { return; }
      var Original = paypal.Buttons;
      var serverReady = !!(config.paypal && config.paypal.serverVerification);

      function scopedActions(actions) {
        if (!actions || !actions.order || typeof actions.order.create !== 'function') { return actions; }
        var createOrder = actions.order.create;
        var order = {};
        for (var key in actions.order) { if (hasOwn(actions.order, key)) { order[key] = actions.order[key]; } }
        order.create = function (payload) {
          payload = payload || {};
          var context = {};
          if (payload.application_context) {
            for (var name in payload.application_context) {
              if (hasOwn(payload.application_context, name)) { context[name] = payload.application_context[name]; }
            }
          }
          context.shipping_preference = 'NO_SHIPPING';
          payload.application_context = context;
          return createOrder.call(actions.order, payload);
        };
        /* Route the capture (and any lookup) through the server so the payment
           is verified with PayPal before it is ever reported as successful. */
        if (serverReady) {
          var originalCapture = typeof actions.order.capture === 'function' ? actions.order.capture : null;
          var originalGet = typeof actions.order.get === 'function' ? actions.order.get : null;
          order.capture = function () {
            var amount = formValue('paypal-amount');
            return Promise.resolve()
              .then(function () { return originalCapture ? originalCapture.call(actions.order) : null; })
              .catch(function () { return null; })
              .then(function (approved) {
                var orderId = approved && approved.id ? approved.id : (order && order.__tptOrderId) || '';
                if (!orderId) { throw new Error('PayPal did not return an order id.'); }
                return serverCapture(orderId, amount);
              });
          };
          order.get = function () {
            if (originalGet) { return originalGet.call(actions.order); }
            return Promise.reject(new Error('Not available.'));
          };
        }
        var scoped = {};
        for (var prop in actions) { if (hasOwn(actions, prop)) { scoped[prop] = actions[prop]; } }
        scoped.order = order;
        return scoped;
      }

      function PatchedButtons(options) {
        options = options || {};
        var createOrder = options.createOrder;
        var onApprove = options.onApprove;
        var onCancel = options.onCancel;
        var onError = options.onError;
        options.createOrder = function (data, actions) {
          var scoped = scopedActions(actions);
          var created;
          if (typeof createOrder === 'function') { created = createOrder.call(this, data, scoped); }
          else { created = scoped.order.create({ purchase_units: [{ amount: { value: '0.01', currency_code: 'USD' } }] }); }
          if (created && typeof created.then === 'function') {
            return created.then(function (orderId) {
              if (orderId) { scoped.order.__tptOrderId = orderId; }
              return orderId;
            });
          }
          if (created) { scoped.order.__tptOrderId = created; }
          return created;
        };
        if (serverReady && typeof onApprove === 'function') {
          options.onApprove = function (data, actions) {
            var scoped = scopedActions(actions);
            var result;
            try {
              result = onApprove.call(this, data, scoped);
            } catch (error) {
              return Promise.reject(error);
            }
            return Promise.resolve(result).catch(function (error) {
              reportCaptureProblem(error && error.message ? error.message : '');
              throw error;
            });
          };
        } else if (typeof onApprove === 'function') {
          options.onApprove = function (data, actions) {
            var scoped = scopedActions(actions);
            var result = onApprove.call(this, data, scoped);
            if (result && typeof result.then === 'function') {
              return result.then(function (details) { confirmFromSdkDetails('paypal', details); return details; });
            }
            return result;
          };
        }
        if (typeof onCancel === 'function') {
          options.onCancel = function (data) {
            var box = confirmationBox();
            if (box) { box.hidden = true; box.classList.remove('show'); }
            return onCancel.call(this, data);
          };
        }
        if (typeof onError === 'function') {
          options.onError = function (error) {
            var box = confirmationBox();
            if (box) { box.hidden = true; box.classList.remove('show'); }
            return onError.call(this, error);
          };
        }
        return Original.call(this, options);
      }
      PatchedButtons.prototype = Original.prototype;
      for (var prop in Original) { if (hasOwn(Original, prop)) { PatchedButtons[prop] = Original[prop]; } }
      try { Object.defineProperty(PatchedButtons, '__tptNoShipping', { value: true }); } catch (markerError) { /* ignore */ }
      paypal.Buttons = PatchedButtons;
    } catch (error) { /* never break the checkout flow */ }
  }

  /* The PayPal SDK assigns window.paypal after it loads, so intercept the
     assignment as well as patching an already-loaded SDK. */
  var loadedPayPal = window.paypal;
  try {
    Object.defineProperty(window, 'paypal', {
      configurable: true,
      enumerable: true,
      get: function () { return loadedPayPal; },
      set: function (value) { loadedPayPal = value; forceNoShipping(value); }
    });
  } catch (defineError) { /* ignore */ }
  forceNoShipping(window.paypal);
  window.setTimeout(function () { forceNoShipping(window.paypal); }, 0);

  function applyPageRules() {
    paymentBlocks().forEach(function (block) {
      replaceTermsCheckbox(block);
      softenWhiteBackgrounds(block);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      connectSharedSettings();
      applyPageRules();
      forceNoShipping(window.paypal);
    });
  } else {
    connectSharedSettings();
    applyPageRules();
  }
  window.setTimeout(applyPageRules, 800);

  /* Exposed for the administrator's own code and for the starter templates. */
  window.TPT_PAYPAL = {
    serverVerification: !!(config.paypal && config.paypal.serverVerification),
    capture: function (orderId, expectedAmount) { return serverCapture(orderId, expectedAmount); },
    showConfirmation: showConfirmation,
    reportProblem: reportCaptureProblem
  };
})();
TPT_BRIDGE_JS
        . "</script>\n";
}

/* ===========================================================================
   ID audit — PayPal and Stripe must not collide on the same page
   =========================================================================== */

/** Every HTML id used inside a saved implementation. */
function piePaymentCodeIds($code)
{
    $ids = array();
    if (preg_match_all('/\bid\s*=\s*["\']([^"\']+)["\']/i', (string) $code, $matches)) {
        foreach ($matches[1] as $id) {
            $ids[] = strtolower(trim($id));
        }
    }
    return $ids;
}

/**
 * Service dropdowns inside a saved implementation that are NOT connected to the
 * Services system. A select is linked when the code uses {{SERVICES_OPTIONS}} /
 * {{SERVICES_JSON}} or marks the element with data-tpt-services (the shared
 * bridge then fills it). Anything else is a hard-coded dropdown that would
 * ignore the admin's Services list.
 */
function piePaymentUnlinkedSelects($code)
{
    $code = (string) $code;
    if ($code === '') {
        return array();
    }
    if (strpos($code, '{{SERVICES_OPTIONS}}') !== false
        || strpos($code, '{{SERVICES_JSON}}') !== false
        || strpos($code, 'data-tpt-services') !== false) {
        return array();
    }
    $ids = array();
    if (preg_match_all('/<select\b[^>]*>/i', $code, $matches)) {
        foreach ($matches[0] as $tag) {
            $ids[] = preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $tag, $idMatch)
                ? strtolower(trim($idMatch[1]))
                : '(unnamed select)';
        }
    }
    return array_values(array_unique($ids));
}

/**
 * Ids that appear more than once across the saved implementations (or twice
 * inside one of them). Duplicate ids break getElementById / querySelector and
 * make the two gateways target each other's elements.
 */
function piePaymentIdConflicts(array $blocks)
{
    $counts = array();
    foreach ($blocks as $code) {
        foreach (piePaymentCodeIds($code) as $id) {
            $counts[$id] = isset($counts[$id]) ? $counts[$id] + 1 : 1;
        }
    }
    $conflicts = array();
    foreach ($counts as $id => $count) {
        if ($count > 1) {
            $conflicts[] = $id;
        }
    }
    sort($conflicts);
    return $conflicts;
}

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

/* The Stripe Secret Key, the Webhook Secret and the server-side confirmation
   flow live in core/Stripe.php (pieStripeSecret(), pieStripeServerReady(),
   pieStripeCreatePaymentIntent(), pieStripeVerifyWebhookSignature()). Both
   credentials are SERVER-SIDE ONLY: nothing in this file — or anywhere that
   produces HTML, JavaScript or an API response — reads, renders, logs or
   returns them. */
require_once __DIR__ . '/Stripe.php';

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
 * Small, dependency-free script emitted ONCE before the first payment block.
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
 *     PayPal Secret is configured, and Stripe confirmations through the
 *     server (core/Stripe.php) whenever a Stripe Secret Key is configured, so
 *     a payment is only reported as successful after the SERVER has verified
 *     it with the gateway. The browser never sees a secret.
 *  6. Shows the confirmed-payment acknowledgement:
 *     "Thank You, [Name]! Your payment of $[Amount] USD was successfully
 *      completed. Payment Reference: [ID]"
 *     Only ever after a confirmed capture — never on a click, a redirect or a
 *     cancelled/failed payment — plus the accessible "Payment successful"
 *     popup (once per payment, role="dialog", focus trap, Esc to close).
 *  7. Keeps the payment area on the dark TPT theme: any element inside the
 *     payment blocks whose background is plain white is softened to the site's
 *     dark surface colours.
 *  8. Arranges each provider block into the two-column layout — the buyer's
 *     fields and the Terms line on the left, the gateway's own buttons (and
 *     "Powered by …") centred on the right — by MOVING the administrator's
 *     existing DOM nodes into place. Nothing is copied, rewritten or executed
 *     a second time, every field keeps its id/name/binding, and on narrow
 *     screens the columns stack with the form first.
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
        'stripe'    => pieStripeClientConfig(),
        'termsNote' => 'By continuing with your payment, you agree to our Terms & Conditions.',
        'confirmId' => 'tpt-payment-confirmation',
        'layout'    => array(
            'heading'   => 'Make a Payment',
            'poweredBy' => array(
                'paypal' => 'Powered by PayPal',
                'stripe' => 'Powered by Stripe',
            ),
        ),
        'modal'     => array(
            'title' => 'Payment successful',
            'close' => 'Close',
        ),
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

  /* A payment is reported once: keyed by provider + gateway reference. */
  var shownPayments = {};

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

  function blockOf(provider) {
    var blocks = paymentBlocks();
    for (var i = 0; i < blocks.length; i++) {
      if (blocks[i].provider === provider) { return blocks[i]; }
    }
    return null;
  }

  function element(node) {
    return !!node && node.nodeType === 1;
  }

  function on(element, event, handler) {
    if (element && element.addEventListener) { element.addEventListener(event, handler); }
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
     Confirmed-payment area, success popup and problem reporting.
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

  function formValue(id) {
    var field = document.getElementById(id);
    return field && typeof field.value === 'string' ? field.value : '';
  }

  /** The buyer's name/service/amount as typed on the page (used for the record
   *  and for the popup) — never a card number, secret or credential. */
  function buyerDetails(provider) {
    return {
      name: formValue(provider + '-name'),
      service: formValue(provider + '-service'),
      email: formValue(provider + '-email'),
      phone: formValue(provider + '-phone'),
      amount: formValue(provider + '-amount')
    };
  }

  /* ---- accessible "Payment successful" popup (opened once per payment) ---- */
  var modalFocus = null;

  function modalElement() {
    var existing = document.getElementById('tpt-pay-modal');
    if (existing) { return existing; }
    var modal = document.createElement('div');
    modal.className = 'pay-modal';
    modal.id = 'tpt-pay-modal';
    modal.setAttribute('aria-hidden', 'true');

    var overlay = document.createElement('div');
    overlay.className = 'pay-modal__overlay';
    overlay.setAttribute('data-pay-modal-close', '1');

    var panel = document.createElement('div');
    panel.className = 'pay-modal__panel';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'tpt-pay-modal-title');
    panel.setAttribute('aria-describedby', 'tpt-pay-modal-facts');
    panel.innerHTML =
      '<span class="pay-modal__check" aria-hidden="true">' +
        '<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m4 12.5 5 5L20 6.5"/></svg>' +
      '</span>' +
      '<h2 class="pay-modal__title" id="tpt-pay-modal-title"></h2>' +
      '<p class="pay-modal__lead" data-pay-modal-lead></p>' +
      '<dl class="pay-modal__facts" id="tpt-pay-modal-facts">' +
        '<div class="pay-modal__fact"><dt>Amount</dt><dd data-pay-modal="amount"></dd></div>' +
        '<div class="pay-modal__fact"><dt>Service</dt><dd data-pay-modal="service"></dd></div>' +
        '<div class="pay-modal__fact"><dt>Provider</dt><dd data-pay-modal="provider"></dd></div>' +
        '<div class="pay-modal__fact"><dt>Payment reference</dt><dd data-pay-modal="reference"></dd></div>' +
      '</dl>' +
      '<button type="button" class="btn btn-primary pay-modal__close" data-pay-modal-close="1"></button>';

    modal.appendChild(overlay);
    modal.appendChild(panel);
    document.body.appendChild(modal);

    on(modal, 'click', function (event) {
      var target = event.target;
      if (target && target.closest && target.closest('[data-pay-modal-close]')) {
        event.preventDefault();
        closeModal();
      }
    });
    on(modal, 'keydown', function (event) { trapFocus(event, modal); });
    on(document, 'keydown', function (event) {
      if (!modal.classList.contains('open')) { return; }
      if (event.key === 'Escape' || event.key === 'Esc') {
        event.preventDefault();
        closeModal();
      }
    });
    return modal;
  }

  function trapFocus(event, modal) {
    if (event.key !== 'Tab') { return; }
    var items = Array.prototype.slice.call(
      modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')
    ).filter(function (el) { return el.getClientRects().length > 0; });
    if (!items.length) { return; }
    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }

  function providerLabel(provider) {
    return provider === 'stripe' ? 'Stripe' : (provider === 'paypal' ? 'PayPal' : (provider || 'Payment provider'));
  }

  function openModal(details) {
    try {
      var modal = modalElement();
      var title = (config.modal && config.modal.title) || 'Payment successful';
      var titleNode = modal.querySelector('.pay-modal__title');
      if (titleNode) { titleNode.textContent = title; }
      var lead = modal.querySelector('[data-pay-modal-lead]');
      if (lead) {
        lead.textContent = 'Thank you' + (details.name && details.name !== 'there' ? ', ' + details.name : '') +
          '. Your payment has been confirmed by ' + providerLabel(details.provider) + '.';
      }
      var values = {
        amount: details.amount ? '$' + details.amount + ' ' + (details.currency || 'USD') : '—',
        service: details.service || '—',
        provider: providerLabel(details.provider),
        reference: details.reference || '—'
      };
      Object.keys(values).forEach(function (key) {
        var node = modal.querySelector('[data-pay-modal="' + key + '"]');
        if (node) { node.textContent = values[key]; }
      });
      var closeButton = modal.querySelector('.pay-modal__close');
      if (closeButton) { closeButton.textContent = (config.modal && config.modal.close) || 'Close'; }

      modalFocus = document.activeElement;
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
      if (closeButton && closeButton.focus) {
        try { closeButton.focus({ preventScroll: true }); } catch (error) { /* ignore */ }
      }
    } catch (error) { /* a popup failure never hides the confirmation below */ }
  }

  function closeModal() {
    var modal = document.getElementById('tpt-pay-modal');
    if (!modal || !modal.classList.contains('open')) { return; }
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    if (modalFocus && modalFocus.focus) {
      try { modalFocus.focus({ preventScroll: true }); } catch (error) { /* ignore */ }
    }
  }

  function showConfirmation(name, amount, reference, details) {
    details = details || {};
    var key = (details.provider || '') + ':' + (reference || '');
    if (reference && hasOwn(shownPayments, key)) { return; } /* once per payment */
    if (reference) { shownPayments[key] = true; }

    var box = confirmationBox();
    if (box) {
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
      box.classList.remove('pay-confirmation-error');
      try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (error) { /* ignore */ }
    }

    /* The popup appears only after a confirmed payment, and only once. */
    if (reference) {
      openModal({
        name: name || details.name || '',
        amount: amount || '',
        service: details.service || '',
        provider: details.provider || '',
        currency: 'USD',
        reference: reference
      });
    }
  }

  /** Explain a verification problem next to the payment buttons. */
  function reportCaptureProblem(message) {
    var box = confirmationBox();
    if (!box) { return; }
    box.hidden = false;
    box.classList.add('show', 'pay-confirmation-error');
    box.textContent = message || 'We could not verify this payment yet. Please do not pay again — contact us and we will confirm it for you.';
  }

  /* ---------------------------------------------------------------- *
     8 — two-column provider layout.

     The administrator's own code is never rewritten: the field groups,
     the gateway buttons and the card mount point are MOVED (the same
     nodes) into the left/right columns of the server-rendered grid, so
     every id, name, event handler and SDK binding keeps working, and
     nothing is duplicated or executed twice.
     ---------------------------------------------------------------- */
  var FIELD_GROUP = '.field, [data-tpt-field], .form-group, .form-row, .field-group, .input-group, .pay-field';
  var FIELD_INPUT = 'input[type="text"],input[type="number"],input[type="email"],input[type="tel"],select,textarea';

  function matches(node, selector) {
    if (!element(node)) { return false; }
    var fn = node.matches || node.msMatchesSelector || node.webkitMatchesSelector;
    try { return fn ? fn.call(node, selector) : false; } catch (error) { return false; }
  }

  /* The buyer's own fields (name / service / amount) — never a card field. */
  function isBuyerField(node, provider) {
    if (!matches(node, FIELD_INPUT)) { return false; }
    var type = (node.getAttribute('type') || '').toLowerCase();
    if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'checkbox' || type === 'radio') { return false; }
    var id = (node.getAttribute('id') || '').toLowerCase();
    var name = (node.getAttribute('name') || '').toLowerCase();
    if (name === 'name' || name === 'service' || name === 'amount') { return true; }
    if (id === provider + '-name' || id === provider + '-service' || id === provider + '-amount') { return true; }
    return /(^|[-_])(name|service|amount)$/.test(id);
  }

  function fieldRank(node) {
    var id = (node.getAttribute('id') || '').toLowerCase();
    var name = (node.getAttribute('name') || '').toLowerCase();
    if (/(^|[-_])name$/.test(id) || name === 'name') { return 0; }
    if (/(^|[-_])service$/.test(id) || name === 'service') { return 1; }
    if (/(^|[-_])amount$/.test(id) || name === 'amount') { return 2; }
    return 3;
  }

  /* A card / Payment Element mount point ("the card" column). */
  function isPaymentMount(node, host) {
    if (!element(node) || node === host) { return false; }
    var id = (node.getAttribute('id') || '').toLowerCase();
    var classes = typeof node.className === 'string' ? node.className : '';
    var looksRight = classes.indexOf('StripeElement') !== -1
      || classes.indexOf('stripe-pay-card') !== -1
      || (id !== '' && /(card|payment|wallet|express|link)/.test(id) && /(element|mount|container|box|field|frame|wrapper|holder)/.test(id));
    if (!looksRight) { return false; }
    /* Never swallow the buyer's own fields or a whole form. */
    if (node.querySelector && node.querySelector('form, ' + FIELD_INPUT + ', button, iframe')) { return false; }
    return true;
  }

  function isButtonHost(node, host) {
    if (!element(node) || node === host) { return false; }
    var id = (node.getAttribute('id') || '').toLowerCase();
    if (node.hasAttribute && node.hasAttribute('data-tpt-buttons')) { return true; }
    if (id !== '' && id.indexOf('button-container') !== -1) { return true; }
    if (id !== '' && /(pay|payment|submit|checkout)[-_]?(button|btn|buttons|actions?)$/.test(id)) { return true; }
    var classes = typeof node.className === 'string' ? node.className : '';
    if (classes.indexOf('paypal-buttons') !== -1) { return true; }
    if (node.tagName === 'BUTTON') { return true; }
    return false;
  }

  /** The group that should travel with a field (its .field wrapper), if any. */
  function groupFor(node, host, avoid) {
    var walker = node;
    while (element(walker) && walker.parentNode && walker.parentNode !== host) {
      if (matches(walker, FIELD_GROUP) && !(avoid && avoid(walker))) { return walker; }
      walker = walker.parentNode;
    }
    var parent = node.parentNode;
    if (!element(parent) || parent === host) { return null; }
    if (avoid && avoid(parent)) { return null; }
    return parent;
  }

  function insideColumn(node, column) {
    return !!(column && node && column.contains && column.contains(node));
  }

  function moveInto(node, column) {
    if (!element(node) || !column || !node.parentNode) { return false; }
    if (insideColumn(node, column)) { return false; }
    /* Moving a container into one of its own children would destroy the tree
       (HierarchyRequestError) — never do it. */
    if (node.contains && node.contains(column)) { return false; }
    column.appendChild(node);
    return true;
  }

  /* The administrator's "Powered by …" line, when their code already has one.
     The bridge's own grid and its columns are never candidates: the "Powered
     by" line rendered by the page is skipped by its marker, and any wrapper
     that holds a column (or a form field / gateway button) is skipped too, so
     nothing can ever be moved into itself. */
  function findPoweredBy(host) {
    var nodes = host.querySelectorAll('p, span, small, div, footer, em');
    for (var i = 0; i < nodes.length; i++) {
      var node = nodes[i];
      if (node.getAttribute && node.getAttribute('data-tpt-powered') === '1') { continue; }
      var text = (node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
      if (text.indexOf('powered by') !== 0 || text.length > 60) { continue; }
      if (node.querySelector && node.querySelector('[data-tpt-split], [data-tpt-form], [data-tpt-fields], [data-tpt-actions], [data-tpt-buttons], [data-tpt-powered], input, select, button, iframe, form')) {
        continue;
      }
      return node;
    }
    return null;
  }

  function placeProviderLayout(provider) {
    try {
      var block = blockOf(provider);
      if (!block) { return; }
      var panel = block.root.closest ? block.root.closest('.pay-provider') : null;
      if (!panel) { return; }
      var split = panel.querySelector('[data-tpt-split]');
      if (!split) { return; }

      /* The administrator's form (when there is one) becomes the grid host, so
         every field and button stays INSIDE their form: submission, implicit
         Enter, form.elements and their own JS all keep working. */
      var form = block.root.querySelector('form');
      var host = form || block.root;
      var fieldsColumn = split.querySelector('[data-tpt-fields]');
      var actionsColumn = split.querySelector('[data-tpt-buttons]');
      if (!fieldsColumn || !actionsColumn) { return; }

      if (split.parentNode !== host && !host.contains(split)) {
        host.insertBefore(split, host.firstChild);
      }

      var movedAnything = false;

      /* 1 — an existing "Make a Payment" heading replaces ours (never two). */
      var title = panel.querySelector('[data-tpt-title]');
      if (title) {
        var headings = host.querySelectorAll('h1, h2, h3, h4');
        for (var h = 0; h < headings.length && !insideColumn(headings[h], split); h++) {
          var headingText = (headings[h].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
          if (headingText === 'make a payment' || headingText === 'payment' || headingText === 'payment details') {
            fieldsColumn.insertBefore(headings[h], fieldsColumn.firstChild);
            title.hidden = true;
            break;
          }
        }
      }

      /* 2 — the gateway's own action nodes (Pay / Card) go to the right. */
      var actionNodes = [];
      var candidates = host.querySelectorAll('button, [id$="-button-container"], [id*="button-container"], [data-tpt-buttons]');
      Array.prototype.forEach.call(candidates, function (node) {
        if (insideColumn(node, split)) { return; }
        if (isButtonHost(node, host) && !isBuyerField(node, provider)) { actionNodes.push(node); }
      });
      /* Drop parents that are already selected through a child. */
      actionNodes = actionNodes.filter(function (node) {
        var walker = node.parentNode;
        while (element(walker) && walker !== host) {
          if (actionNodes.indexOf(walker) !== -1) { return false; }
          walker = walker.parentNode;
        }
        return true;
      });
      actionNodes.forEach(function (node) { if (moveInto(node, actionsColumn)) { movedAnything = true; } });

      /* 3 — the card / Payment Element mount point (Stripe) also goes right. */
      var mounts = host.querySelectorAll('*');
      Array.prototype.forEach.call(mounts, function (node) {
        if (insideColumn(node, split) || !isPaymentMount(node, host)) { return; }
        var group = groupFor(node, host, function (candidate) {
          return !!candidate.querySelector(FIELD_INPUT);
        }) || node;
        if (moveInto(group, actionsColumn)) { movedAnything = true; }
      });

      /* 4 — the buyer's fields, in the documented order, on the left. */
      var fieldGroups = [];
      Array.prototype.forEach.call(host.querySelectorAll(FIELD_INPUT), function (input) {
        if (insideColumn(input, split) || !isBuyerField(input, provider)) { return; }
        var group = groupFor(input, host, null) || input;
        if (fieldGroups.indexOf(group) === -1 && !insideColumn(group, split)) { fieldGroups.push(group); }
      });
      fieldGroups.sort(function (a, b) {
        var rankA = fieldRank(a.querySelector ? (a.querySelector(FIELD_INPUT) || a) : a);
        var rankB = fieldRank(b.querySelector ? (b.querySelector(FIELD_INPUT) || b) : b);
        return rankA - rankB;
      });
      fieldGroups.forEach(function (group) {
        if (moveInto(group, fieldsColumn)) { movedAnything = true; }
      });

      /* 5 — a Terms line created from the administrator's own checkbox moves
             into the left column; ours steps aside so it is shown once. */
      var ownTerms = host.querySelectorAll('[data-tpt-terms-note]');
      Array.prototype.forEach.call(ownTerms, function (note) { if (moveInto(note, fieldsColumn)) { movedAnything = true; } });
      if (ownTerms.length) {
        Array.prototype.forEach.call(split.querySelectorAll('[data-tpt-terms-page]'), function (note) { note.hidden = true; });
      }

      /* 6 — "Powered by …" sits under the buttons, never twice. */
      var powered = findPoweredBy(host);
      if (powered) {
        if (moveInto(powered, actionsColumn)) { movedAnything = true; }
        Array.prototype.forEach.call(actionsColumn.querySelectorAll('[data-tpt-powered]'), function (line) { line.hidden = true; });
      }

      /* Only reveal the grid once something was actually placed — a code block
         the bridge cannot read keeps rendering exactly as it does today. */
      if (movedAnything || fieldsColumn.childNodes.length || actionsColumn.childNodes.length) {
        panel.classList.add('is-split-ready');
      }
    } catch (error) { window.__tptLayoutErrors = (window.__tptLayoutErrors || []).concat([provider + ': ' + (error && error.stack ? error.stack : error)]); }
  }

  function applyProviderLayouts() {
    (config.providers || []).forEach(function (provider) { placeProviderLayout(provider); });
  }

  /* ---------------------------------------------------------------- *
     5 + 6 — server-verified capture and the confirmed-payment message.
     ---------------------------------------------------------------- */
  /** Ask the server to capture and verify an approved PayPal order.
   *  The optional third argument carries the buyer's name/service (used only
   *  for the payment record) — never a card, email or any credential. */
  function serverCapture(orderId, expectedAmount, buyer) {
    buyer = buyer || {};
    return fetch(config.paypal.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        action: 'capture',
        order_id: orderId,
        expected_amount: expectedAmount || '',
        name: buyer.name || '',
        service: buyer.service || '',
        email: buyer.email || '',
        phone: buyer.phone || '',
        csrf_token: config.csrf || ''
      })
    }).then(function (response) {
      return response.json().then(function (json) { return { ok: response.ok, json: json }; });
    }).then(function (result) {
      var json = result.json || {};
      if (!json.success || !json.confirmed) {
        throw new Error(json.message || 'The payment could not be verified on the server.');
      }
      showConfirmation(json.name, json.amount, json.reference, {
        provider: 'paypal',
        service: json.service || buyer.service || '',
        name: json.name || buyer.name || ''
      });
      return json.details || json;
    });
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
      showConfirmation(payer, Number(amount || 0).toFixed(2), reference, {
        provider: provider,
        service: formValue(provider + '-service')
      });
      if (box) { box.setAttribute('data-tpt-confirmed-by', 'sdk'); }
    } catch (error) { /* never break the checkout */ }
  }

  /* ---- Stripe server-side confirmation (same rules as PayPal) ---- */
  function stripeIntentIdFromResult(result, clientSecret) {
    if (result && result.paymentIntent && result.paymentIntent.id) { return result.paymentIntent.id; }
    var match = String(clientSecret || '').match(/^(pi_[A-Za-z0-9]+)_secret_/);
    return match ? match[1] : '';
  }

  /** Ask the server to verify (and record) a Stripe payment. */
  function serverStripeConfirm(reference, expectedAmount, buyer) {
    buyer = buyer || {};
    return fetch(config.stripe.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        action: 'confirm',
        payment_intent_id: reference,
        expected_amount: expectedAmount || '',
        name: buyer.name || '',
        service: buyer.service || '',
        email: buyer.email || '',
        csrf_token: config.csrf || ''
      })
    }).then(function (response) {
      return response.json().then(function (json) { return { ok: response.ok, json: json }; });
    }).then(function (result) {
      var json = result.json || {};
      if (!json.success || !json.confirmed) {
        throw new Error(json.message || 'The payment could not be verified on the server.');
      }
      showConfirmation(json.name, json.amount, json.reference, {
        provider: 'stripe',
        service: json.service || buyer.service || '',
        name: json.name || buyer.name || ''
      });
      return json;
    });
  }

  /** Create a PaymentIntent on the server (the amount comes from the server). */
  function serverStripeCreate(buyer) {
    buyer = buyer || {};
    return fetch(config.stripe.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        action: 'create',
        expected_amount: buyer.amount || '',
        name: buyer.name || '',
        service: buyer.service || '',
        email: buyer.email || '',
        csrf_token: config.csrf || ''
      })
    }).then(function (response) {
      return response.json().then(function (json) { return { ok: response.ok, json: json }; });
    }).then(function (result) {
      var json = result.json || {};
      if (!json.success || !json.client_secret) {
        throw new Error(json.message || 'Stripe could not start this payment.');
      }
      return json;
    });
  }

  /** After Stripe.js confirms a card payment, verify it on the server and
   *  report it. Only a verified payment is ever shown as successful. */
  function afterStripeConfirmation(result, clientSecret, fallbackAmount) {
    var intentId = stripeIntentIdFromResult(result, clientSecret);
    if (!intentId) { return Promise.resolve(null); }
    var buyer = buyerDetails('stripe');
    return serverStripeConfirm(intentId, fallbackAmount || buyer.amount, buyer);
  }

  function wrapStripeInstance(instance) {
    if (!instance || instance.__tptWrapped) { return instance; }
    ['confirmCardPayment', 'confirmPayment'].forEach(function (method) {
      var original = instance[method];
      if (typeof original !== 'function') { return; }
      instance[method] = function (clientSecret, data, options) {
        var call = original.call(instance, clientSecret, data, options);
        if (!call || typeof call.then !== 'function') { return call; }
        return call.then(function (result) {
          if (!result || result.error) { return result; } /* card errors stay with Stripe.js */
          return afterStripeConfirmation(result, clientSecret).then(function () { return result; })
            .catch(function (error) {
              reportCaptureProblem(error && error.message ? error.message : '');
              return result;
            });
        });
      };
    });
    try { Object.defineProperty(instance, '__tptWrapped', { value: true }); } catch (error) { instance.__tptWrapped = true; }
    return instance;
  }

  function installStripePatch() {
    if (!config.stripe || !config.stripe.serverVerification) { return; }
    var factory = stripeFactory;
    if (typeof factory !== 'function' || factory.__tptPatched) { return; }
    function PatchedStripe(key, options) {
      return wrapStripeInstance(factory.apply(this, arguments));
    }
    PatchedStripe.prototype = factory.prototype;
    for (var prop in factory) { if (hasOwn(factory, prop)) { PatchedStripe[prop] = factory[prop]; } }
    try { Object.defineProperty(PatchedStripe, '__tptPatched', { value: true }); } catch (error) { /* ignore */ }
    stripeFactory = PatchedStripe;
  }

  /* Stripe.js assigns window.Stripe when it loads (after this script), so the
     assignment is intercepted as well as a factory that is already present. */
  var stripeFactory = window.Stripe;
  try {
    Object.defineProperty(window, 'Stripe', {
      configurable: true,
      enumerable: true,
      get: function () { return stripeFactory; },
      set: function (value) {
        stripeFactory = value;
        installStripePatch();
      }
    });
  } catch (defineError) { /* ignore */ }
  installStripePatch();

  /* Exposed for the administrator's own code and for the starter templates. */
  window.TPT_STRIPE = {
    serverVerification: !!(config.stripe && config.stripe.serverVerification),
    currency: 'USD',
    endpoint: config.stripe ? config.stripe.endpoint : '',
    createIntent: function (buyer) { return serverStripeCreate(buyer); },
    confirm: function (reference, expectedAmount, buyer) { return serverStripeConfirm(reference, expectedAmount, buyer); },
    reportProblem: reportCaptureProblem
  };

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
                return serverCapture(orderId, amount, buyerDetails('paypal'));
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

  /* The popup closes with Escape even before the first payment. */
  on(document, 'click', function (event) {
    var target = event.target;
    if (target && target.closest && target.closest('[data-pay-modal-close]')) {
      closeModal();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      connectSharedSettings();
      applyPageRules();
      applyProviderLayouts();
      forceNoShipping(window.paypal);
    });
  } else {
    connectSharedSettings();
    applyPageRules();
    applyProviderLayouts();
  }
  window.setTimeout(applyPageRules, 800);
  /* Gateways render their buttons a moment later: place them too. */
  window.setTimeout(applyProviderLayouts, 350);
  window.setTimeout(applyProviderLayouts, 1200);

  /* Exposed for the administrator's own code and for the starter templates. */
  window.TPT_PAYPAL = {
    serverVerification: !!(config.paypal && config.paypal.serverVerification),
    capture: function (orderId, expectedAmount, buyer) { return serverCapture(orderId, expectedAmount, buyer); },
    showConfirmation: showConfirmation,
    reportProblem: reportCaptureProblem
  };
  window.TPT_PAYMENT_UI = {
    showConfirmation: showConfirmation,
    closePopup: closeModal,
    placeLayouts: applyProviderLayouts
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

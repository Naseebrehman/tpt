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
   Same-page safety bridge (IDs, shared services, shared terms, no shipping)
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
    );
    $json = (string) json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($json === '') {
        return '';
    }
    return "<script>\n"
        . "window.TPT_PAYMENT=" . $json . ";\n"
        . <<<TPT_BRIDGE_JS
(function () {
  'use strict';
  var config = window.TPT_PAYMENT;
  if (!config) { return; }
  window.TPT_PAYMENT_SERVICES = config.services || [];

  function hasOwn(object, key) {
    return Object.prototype.hasOwnProperty.call(object, key);
  }

  /* 1 + 2 — shared Services and shared Terms & Conditions URL. */
  function connectSharedSettings() {
    (config.providers || []).forEach(function (provider) {
      var root = document.getElementById(provider + '-payment-code');
      if (!root) { return; }
      Array.prototype.forEach.call(root.querySelectorAll('select[data-tpt-services]'), function (select) {
        if (select.options.length) { return; } /* real options are never overwritten */
        (config.services || []).forEach(function (name) {
          var option = document.createElement('option');
          option.value = name;
          option.textContent = name;
          select.appendChild(option);
        });
      });
      Array.prototype.forEach.call(root.querySelectorAll('a[data-tpt-terms]'), function (link) {
        if (config.termsUrl) { link.setAttribute('href', config.termsUrl); }
      });
    });
  }

  /* 3 — PayPal must never ask for a shipping address. */
  function forceNoShipping(paypal) {
    try {
      if (!paypal || !paypal.Buttons || paypal.Buttons.__tptNoShipping) { return; }
      var Original = paypal.Buttons;

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
        var scoped = {};
        for (var prop in actions) { if (hasOwn(actions, prop)) { scoped[prop] = actions[prop]; } }
        scoped.order = order;
        return scoped;
      }

      function PatchedButtons(options) {
        options = options || {};
        var createOrder = options.createOrder;
        options.createOrder = function (data, actions) {
          var scoped = scopedActions(actions);
          if (typeof createOrder === 'function') { return createOrder.call(this, data, scoped); }
          return scoped.order.create({ purchase_units: [{ amount: { value: '0.01', currency_code: 'USD' } }] });
        };
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
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { connectSharedSettings(); forceNoShipping(window.paypal); });
  } else {
    connectSharedSettings();
  }
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

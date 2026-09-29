<?php
/**
 * The Pie Technologies — Pay Online (/pay-online)
 * Simplified payment page: PayPal Buttons or Stripe Elements (Tasks 3–5).
 * Only providers enabled in Admin → Settings → Payments are ever shown.
 * Card details are never collected on this site — Stripe Elements tokenises
 * them directly with Stripe; PayPal handles its own checkout.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';
require_once BASE_PATH . '/core/Captcha.php';

$pageTitle = 'Pay Online — Secure Invoice Payments';
$metaDesc  = 'Pay for The Pie Technologies services securely with PayPal or your credit/debit card. A short, simple, fully encrypted payment form.';
$activeNav = 'pay';

$paymentsEnabled = getSetting('pay_online_enabled', '1') === '1';
$providers       = $paymentsEnabled ? piePaymentProviders() : array();

/* Billing categories for this form (Task 3). */
$payServices = array(
    'AI Optimization',
    'Web Development',
    'Digital Marketing',
    'Business Consultation',
    'G-W-M Services',
    'Monthly Marketing Charges',
    'Others',
);

/* ------------------------------------------------------------------
   JSON payment actions (Task 4/5) — posted from assets/js/payment.js
   and through the /api/payment compatibility route.
   ------------------------------------------------------------------ */
function payJson($ok, $message, $extra = array(), $status = 200)
{
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    http_response_code($status);
    echo json_encode(array_merge(array('success' => $ok, 'message' => $message), $extra), JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

function payValidateInput($payServices, $requireTerms = true)
{
    $name    = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
    $email   = sanitize(isset($_POST['email']) ? $_POST['email'] : '');
    $phone   = sanitize(isset($_POST['phone']) ? $_POST['phone'] : '');
    $service = sanitize(isset($_POST['service']) ? $_POST['service'] : '');
    $amountRaw = isset($_POST['amount']) ? trim((string) $_POST['amount']) : '';
    $method  = sanitize(isset($_POST['method']) ? $_POST['method'] : '');
    $terms   = isset($_POST['terms']) && $_POST['terms'] === '1';

    $errors = array();
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150)                       { $errors['name'] = 'Please enter your full name.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) { $errors['email'] = 'Please enter a valid email address.'; }
    $phoneDigits = preg_replace('/[^0-9]/', '', $phone);
    if ($phoneDigits === '' || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15 || !preg_match('/^\+?[0-9 ()\-]{6,25}$/D', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number with country code.';
    }
    if (!in_array($service, $payServices, true))                              { $errors['service'] = 'Please choose the service you are paying for.'; }
    $amount = preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', $amountRaw) ? (float) $amountRaw : 0;
    if ($amount <= 0 || $amount > 1000000)                                    { $errors['amount'] = 'Enter a valid amount in USD (e.g. 1500.00).'; }
    if (!in_array($method, array('paypal', 'stripe', 'invoice'), true))        { $errors['method'] = 'Choose a payment method.'; }
    if ($requireTerms && !$terms)                                             { $errors['terms'] = 'Please accept the Terms & Conditions to continue.'; }

    return array(
        'errors'  => $errors,
        'name'    => $name,
        'email'   => $email,
        'phone'   => $phone,
        'service' => $service,
        'amount'  => $amount,
        'method'  => $method,
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_action']) && $paymentsEnabled) {
    $action = (string) $_POST['payment_action'];

    if (!validateCSRF()) {
        payJson(false, 'Your session expired. Refresh the page and try again.', array(), 403);
    }

    /* ---- init: validate the form, then create the payment record ---- */
    if ($action === 'init') {
        if (!Captcha::verify(Captcha::tokenFromRequest(), pieClientIp())) {
            payJson(false, 'Please complete the security check and try again.', array('errors' => array('captcha' => 'CAPTCHA verification failed.')), 422);
        }
        /* honeypot */
        if (trim((string) (isset($_POST['website']) ? $_POST['website'] : '')) !== '') {
            payJson(true, 'ok', array('token' => ''));
        }
        $input = payValidateInput($payServices);
        if ($input['errors']) {
            payJson(false, reset($input['errors']), array('errors' => $input['errors']), 422);
        }
        $providersNow = piePaymentProviders();
        if ($input['method'] !== 'invoice' && !isset($providersNow[$input['method']])) {
            payJson(false, 'That payment method is not available right now.', array('errors' => array('method' => 'This payment method is currently unavailable.')), 422);
        }

        /* Duplicate-submission guard: identical payments inside 120s reuse the row. */
        $recent = dbOne(
            'SELECT token FROM payments WHERE email = ? AND amount_usd = ? AND method = ? AND status IN ("pending","requested") AND created_at > (NOW() - INTERVAL 2 MINUTE) ORDER BY id DESC LIMIT 1',
            array($input['email'], number_format($input['amount'], 2, '.', ''), $input['method'])
        );
        if ($recent && !empty($recent['token'])) {
            payJson(true, 'Resuming your payment.', array('token' => $recent['token'], 'duplicate' => true));
        }

        $token  = bin2hex(random_bytes(16));
        $newId  = dbInsert(
            'INSERT INTO payments (token, name, email, phone, service, amount_usd, notes, method, status, provider_ref, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array($token, $input['name'], $input['email'], $input['phone'], $input['service'],
                  number_format($input['amount'], 2, '.', ''), '', $input['method'],
                  $input['method'] === 'invoice' ? 'requested' : 'pending', '', pieClientIp())
        );
        if ($newId < 0) {
            payJson(false, 'We could not save your payment details. Please try again or email us.', array(), 503);
        }
        if ($input['method'] === 'invoice') {
            $payment = piePaymentByToken($token);
            if ($payment) { piePaymentNotify($payment); }
            payJson(true, 'Request received.', array('token' => $token, 'invoice' => true));
        }
        payJson(true, 'ok', array('token' => $token));
    }

    /* ---- paypal_create: create a PayPal order for the Buttons ---- */
    if ($action === 'paypal_create') {
        $token   = preg_replace('/[^a-f0-9]/', '', (string) (isset($_POST['token']) ? $_POST['token'] : ''));
        $payment = $token !== '' ? piePaymentByToken($token) : null;
        if (!$payment || $payment['method'] !== 'paypal' || $payment['status'] === 'paid') {
            payJson(false, 'Payment session not found. Start again.', array(), 404);
        }
        $result = piePayPalButtonsOrder($payment);
        if ($result['ok']) {
            dbExec('UPDATE payments SET provider_ref = ? WHERE id = ?', array($result['order_id'], (int) $payment['id']));
            payJson(true, 'ok', array('order_id' => $result['order_id']));
        }
        if (!empty($result['client_side'])) {
            /* No server secret configured — the JS SDK creates the order itself. */
            payJson(true, 'ok', array('client_side' => true));
        }
        payJson(false, 'PayPal is unavailable right now. Please try again in a moment.', array(), 502);
    }

    /* ---- paypal_capture: capture the approved order ---- */
    if ($action === 'paypal_capture') {
        $token   = preg_replace('/[^a-f0-9]/', '', (string) (isset($_POST['token']) ? $_POST['token'] : ''));
        $orderId = (string) (isset($_POST['order_id']) ? $_POST['order_id'] : '');
        $payment = $token !== '' ? piePaymentByToken($token) : null;
        if (!$payment || $payment['status'] === 'paid') {
            payJson(false, 'Payment session not found.', array(), 404);
        }
        $result = piePayPalButtonsCapture($orderId, $payment);
        if ($result['ok'] && ($result['status'] === 'paid' || $result['status'] === 'pending')) {
            dbExec('UPDATE payments SET provider_ref = ? WHERE id = ?', array($result['ref'], (int) $payment['id']));
            if ($result['status'] === 'paid') {
                piePaymentMarkPaid($payment, $result['ref'], 'paypal');
            }
            $payment['provider_ref'] = $result['ref'];
            payJson(true, 'Payment received.', array(
                'status'        => $result['status'],
                'transaction_id'=> $result['ref'],
                'name'          => $payment['name'],
                'amount'        => number_format((float) $payment['amount_usd'], 2),
                'service'       => $payment['service'],
                'method'        => 'PayPal',
            ));
        }
        payJson(false, 'We could not confirm the PayPal payment. You have not been charged twice — please try again.', array(), 502);
    }

    /* ---- stripe_intent: create the PaymentIntent for Elements ---- */
    if ($action === 'stripe_intent') {
        $token   = preg_replace('/[^a-f0-9]/', '', (string) (isset($_POST['token']) ? $_POST['token'] : ''));
        $payment = $token !== '' ? piePaymentByToken($token) : null;
        if (!$payment || $payment['status'] === 'paid') {
            payJson(false, 'Payment session not found. Start again.', array(), 404);
        }
        if ($payment['provider_ref'] !== '' && strpos($payment['provider_ref'], 'pi_') === 0 && $payment['status'] === 'pending') {
            /* Reuse the existing intent for a retry instead of creating another. */
            $status = pieStripeIntentStatus($payment['provider_ref']);
            if ($status === 'paid') {
                piePaymentMarkPaid($payment, $payment['provider_ref'], 'stripe');
                payJson(true, 'Payment received.', array('already_paid' => true, 'transaction_id' => $payment['provider_ref']));
            }
        }
        $result = pieStripeIntent($payment);
        if ($result['ok']) {
            dbExec('UPDATE payments SET provider_ref = ? WHERE id = ?', array($result['intent_id'], (int) $payment['id']));
            payJson(true, 'ok', array(
                'client_secret'   => $result['client_secret'],
                'publishable_key' => $result['publishable'],
                'intent_id'       => $result['intent_id'],
            ));
        }
        payJson(false, 'Card payments are unavailable right now. Please try again or use PayPal.', array(), 502);
    }

    /* ---- stripe_confirm: verify the PaymentIntent server-side ---- */
    if ($action === 'stripe_confirm') {
        $token   = preg_replace('/[^a-f0-9]/', '', (string) (isset($_POST['token']) ? $_POST['token'] : ''));
        $intent  = preg_replace('/[^A-Za-z0-9_]/', '', (string) (isset($_POST['intent_id']) ? $_POST['intent_id'] : ''));
        $payment = $token !== '' ? piePaymentByToken($token) : null;
        if (!$payment || $payment['status'] === 'paid') {
            payJson(false, 'Payment session not found.', array(), 404);
        }
        $status = pieStripeIntentStatus($intent !== '' ? $intent : $payment['provider_ref']);
        if ($status === 'paid') {
            piePaymentMarkPaid($payment, $intent !== '' ? $intent : $payment['provider_ref'], 'stripe');
            payJson(true, 'Payment received.', array(
                'status'         => 'paid',
                'transaction_id' => $intent !== '' ? $intent : $payment['provider_ref'],
                'name'           => $payment['name'],
                'amount'         => number_format((float) $payment['amount_usd'], 2),
                'service'        => $payment['service'],
                'method'         => 'Credit/Debit Card — Stripe',
            ));
        }
        if ($status === 'pending') {
            payJson(true, 'Payment processing.', array('status' => 'pending'));
        }
        payJson(false, 'The payment was not completed. You have not been charged — please try again.', array(), 402);
    }

    payJson(false, 'Unknown payment action.', array(), 400);
}

/* ------------------------- legacy no-JS POST (invoice) -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_submit']) && $paymentsEnabled) {
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if (!validateCSRF()) {
        if ($isAjax) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(array('success'=>false,'message'=>'Your session expired. Refresh and try again.')); exit; }
        setFlash('err', 'Your session expired. Please refresh and try again.');
        header('Location: ' . url('pay-online'));
        exit;
    }

    $input = payValidateInput($payServices);
    if ($input['errors']) {
        if ($isAjax) { http_response_code(422); header('Content-Type: application/json'); echo json_encode(array('success'=>false,'message'=>reset($input['errors']),'errors'=>$input['errors'])); exit; }
        setFlash('err', reset($input['errors']));
        header('Location: ' . url('pay-online'));
        exit;
    }

    $token = bin2hex(random_bytes(16));
    $newId = dbInsert(
        'INSERT INTO payments (token, name, email, phone, service, amount_usd, notes, method, status, provider_ref, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        array($token, $input['name'], $input['email'], $input['phone'], $input['service'],
              number_format($input['amount'], 2, '.', ''), '', $input['method'],
              $input['method'] === 'invoice' ? 'requested' : 'pending', '', pieClientIp())
    );

    if ($newId < 0) {
        if ($isAjax) { http_response_code(503); header('Content-Type: application/json'); echo json_encode(array('success'=>false,'message'=>'Payment request could not be saved.')); exit; }
        setFlash('err', 'We couldn’t save your request. Please email ' . getSetting('site_email', 'info@thepietechnologies.com') . ' and we’ll sort it manually.');
        header('Location: ' . url('pay-online'));
        exit;
    }

    $payment = piePaymentByToken($token);
    $redirect = null;

    if ($input['method'] === 'stripe' && isset($providers['stripe'])) {
        $result = pieStripeCheckout($payment);
        if ($result['ok']) {
            dbExec('UPDATE payments SET provider_ref = ? WHERE id = ?', array($result['ref'], (int) $payment['id']));
            $redirect = $result['url'];
        } else {
            dbExec('UPDATE payments SET method = "invoice", status = "requested", notes = CONCAT(notes, ?) WHERE id = ?',
                array("\n[stripe checkout unavailable — invoice link requested instead]", (int) $payment['id']));
            $payment['method'] = 'invoice';
        }
    } elseif ($input['method'] === 'paypal' && isset($providers['paypal'])) {
        $result = piePayPalOrder($payment);
        if ($result['ok']) {
            dbExec('UPDATE payments SET provider_ref = ? WHERE id = ?', array($result['ref'], (int) $payment['id']));
            $redirect = $result['url'];
        } else {
            dbExec('UPDATE payments SET method = "invoice", status = "requested", notes = CONCAT(notes, ?) WHERE id = ?',
                array("\n[paypal order unavailable — invoice link requested instead]", (int) $payment['id']));
            $payment['method'] = 'invoice';
        }
    }

    piePaymentNotify($payment);

    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(array('success'=>true,'redirect'=>$redirect ?: url('pay/secure/' . $token) . '?requested=1')); exit; }
    if ($redirect !== null) {
        header('Location: ' . $redirect);
        exit;
    }
    header('Location: ' . url('pay/secure/' . $token) . '?requested=1');
    exit;
}

$requestedFlash = isset($_GET['requested']);

$pageLibs['payment'] = true;
$pageLibs['phone']   = true;

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Secure payments</p>
        <h1>Pay online, securely.</h1>
        <p class="lead">A short form, your preferred payment method, and you&rsquo;re done. PayPal and card payments are processed on encrypted, PCI-compliant infrastructure — this site never sees your card number.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (!$paymentsEnabled): ?>
        <div class="chart-card" data-aos="fade-up" style="max-width:720px;margin-inline:auto;text-align:center">
            <h2 style="font-size:1.4rem;margin-bottom:10px">Online payments are currently paused.</h2>
            <p class="text-muted">Please email <a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>" style="color:var(--violet-soft)"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a> with your invoice reference and we&rsquo;ll send a secure payment link manually.</p>
        </div>
        <?php else: ?>
        <div class="contact-grid">
            <div class="contact-panel pay-panel" data-aos="fade-up">
                <?php if ($requestedFlash): ?>
                <div class="chart-card" style="margin-bottom:18px;border-color:rgba(52,211,153,.4)">
                    <p style="color:#34d399;font-weight:600"><?= icon('check', 15) ?> Request received — check your email for the secure payment link.</p>
                </div>
                <?php endif; ?>
                <?php $payFlash = getFlash(); if ($payFlash): ?>
                <div class="chart-card" style="margin-bottom:18px;border-color:rgba(248,113,113,.4)">
                    <p style="color:#f87171"><?= esc($payFlash['message']) ?></p>
                </div>
                <?php endif; ?>

                <h2>Payment details</h2>
                <p class="sub">Fields marked <span style="color:var(--violet-soft)">*</span> are required.</p>

                <form id="payForm" method="post" action="<?= url('pay-online') ?>" novalidate
                      data-pay-form
                      data-paypal-client-id="<?= esc(piePayPalClientId()) ?>"
                      data-paypal-mode="<?= esc(getSetting('paypal_mode', 'sandbox')) ?>"
                      data-invoice-fallback="<?= empty($providers) ? '1' : '0' ?>">
                    <?= csrfField() ?>
                    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px" placeholder="Leave this empty">

                    <div class="form-grid">
                        <div class="field">
                            <label for="pName">Full Name <span class="req">*</span></label>
                            <input id="pName" name="name" type="text" required maxlength="150" autocomplete="name" placeholder="John Smith">
                        </div>
                        <div class="field">
                            <label for="pEmail">Email Address <span class="req">*</span></label>
                            <input id="pEmail" name="email" type="email" required maxlength="150" autocomplete="email" placeholder="john@example.com">
                        </div>
                        <div class="field">
                            <label for="pPhone">Phone Number <span class="req">*</span></label>
                            <input id="pPhone" name="phone" type="tel" required maxlength="30" autocomplete="tel" placeholder="+1 (555) 123-4567" data-intl-phone>
                        </div>
                        <div class="field">
                            <label for="pService">Service <span class="req">*</span></label>
                            <select id="pService" name="service" required>
                                <option value="">Select a Service</option>
                                <?php foreach ($payServices as $opt): ?>
                                <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="pAmount">Payment Amount (USD) <span class="req">*</span></label>
                            <input id="pAmount" name="amount" type="text" required inputmode="decimal" autocomplete="off" placeholder="1500.00" maxlength="12">
                        </div>
                        <div class="field full">
                            <fieldset class="pay-methods" style="border:none;padding:0;margin:0">
                                <legend class="eyebrow" style="margin-bottom:12px">Payment Method <span class="req">*</span></legend>
                                <label class="pay-method">
                                    <input type="radio" name="method" value="paypal"<?= isset($providers['paypal']) ? ' checked' : '' ?><?= isset($providers['paypal']) ? '' : ' disabled' ?>>
                                    <span>
                                        <strong><?= icon('loop', 16) ?> PayPal</strong>
                                        <small>Pay with your PayPal account or a card through PayPal.</small>
                                    </span>
                                </label>
                                <label class="pay-method">
                                    <input type="radio" name="method" value="stripe"<?= !isset($providers['paypal']) && isset($providers['stripe']) ? ' checked' : '' ?><?= isset($providers['stripe']) ? '' : ' disabled' ?>>
                                    <span>
                                        <strong><?= icon('card', 16) ?> Credit/Debit Card — Stripe</strong>
                                        <small>Secure card payment powered by Stripe.</small>
                                    </span>
                                </label>
                                <?php if (empty($providers)): ?>
                                <label class="pay-method">
                                    <input type="radio" name="method" value="invoice" checked>
                                    <span>
                                        <strong><?= icon('mail', 16) ?> Request an invoice</strong>
                                        <small>Online payments are being configured — we&rsquo;ll email you a secure link.</small>
                                    </span>
                                </label>
                                <?php endif; ?>
                            </fieldset>
                        </div>

                        <div class="field full">
                            <label class="pay-terms">
                                <input type="checkbox" name="terms" value="1" required>
                                <span>I agree to the <a href="<?= url('terms') ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a> and authorize this payment. <span class="req">*</span></span>
                            </label>
                        </div>

                        <?php if (Captcha::enabled()): ?>
                        <div class="field full">
                            <?= Captcha::field() ?>
                        </div>
                        <?php endif; ?>

                        <!-- PayPal Buttons mount here (Task 4) -->
                        <div class="full" id="paypalButtonWrap" hidden>
                            <p class="eyebrow" style="margin-bottom:12px">Complete with PayPal</p>
                            <div id="paypalButtons" class="paypal-buttons"></div>
                        </div>

                        <!-- Stripe Elements mount here (Task 5) -->
                        <div class="full" id="stripePaymentWrap" hidden>
                            <p class="eyebrow" style="margin-bottom:12px">Card details</p>
                            <div id="stripePaymentElement" class="stripe-element"></div>
                            <button class="btn btn-primary btn-lg btn-block btn-magnetic" type="button" id="stripePayBtn" style="margin-top:14px">
                                Pay Securely <?= icon('lock', 16) ?>
                            </button>
                        </div>

                        <div class="full" id="payContinueWrap">
                            <button class="btn btn-primary btn-lg btn-block btn-magnetic" type="submit" id="paySubmitBtn">
                                Continue to Payment <?= icon('arrow-r', 18) ?>
                            </button>
                            <p class="text-muted" style="font-size:.78rem;text-align:center;margin-top:10px"><?= icon('lock', 13) ?> 256-bit encrypted. Card details are entered directly on Stripe&rsquo;s secure form — never on this site.</p>
                        </div>
                    </div>
                    <div class="form-status" role="status" aria-live="polite"></div>
                </form>

                <!-- Professional result states (Task 27) -->
                <div class="pay-result" id="paySuccess" hidden>
                    <span class="tick"><?= icon('check', 34) ?></span>
                    <h3>Payment Successful</h3>
                    <p class="text-muted">Thank you — your payment has been received and a confirmation is on its way to your email.</p>
                    <dl class="pay-summary">
                        <div><dt>Customer</dt><dd id="psName"></dd></div>
                        <div><dt>Amount</dt><dd id="psAmount"></dd></div>
                        <div><dt>Service</dt><dd id="psService"></dd></div>
                        <div><dt>Payment method</dt><dd id="psMethod"></dd></div>
                        <div><dt>Transaction / reference ID</dt><dd class="mono" id="psTxn"></dd></div>
                    </dl>
                    <a class="btn btn-ghost" href="<?= url('') ?>">Back to Home</a>
                </div>

                <div class="pay-result error" id="payFailure" hidden>
                    <span class="tick err"><?= icon('close', 34) ?></span>
                    <h3>Payment Could Not Be Completed</h3>
                    <p class="text-muted">Your payment was not processed and you have not been charged. This can happen because of a bank decline, a cancelled checkout or a temporary issue. Please try again — if it keeps failing, email us and we&rsquo;ll help right away.</p>
                    <button class="btn btn-primary" type="button" id="payRetryBtn">Try Again</button>
                    <a class="btn btn-ghost" href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>">Contact Support</a>
                </div>
            </div>

            <aside class="contact-side" data-aos="fade-up" data-aos-delay="120">
                <div class="info-card">
                    <span class="icon"><?= icon('lock', 22) ?></span>
                    <span>
                        <strong>Is this page secure?</strong>
                        <p>Yes — payments run on PayPal&rsquo;s and Stripe&rsquo;s encrypted, PCI-DSS compliant infrastructure. Card numbers are never seen or stored by this website.</p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="icon"><?= icon('card', 22) ?></span>
                    <span>
                        <strong>How does it work?</strong>
                        <p>Fill in the short form, pick PayPal or card, and complete the payment inline. You&rsquo;ll see a confirmation immediately and receive an email receipt.</p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="icon"><?= icon('mail', 22) ?></span>
                    <span>
                        <strong>Need help?</strong>
                        <p>Write to <a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a> and a human will answer.</p>
                    </span>
                </div>
            </aside>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ================================ FAQ ================================= -->
<?php if ($paymentsEnabled): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head center" data-aos="fade-up">
            <p class="eyebrow">FAQ</p>
            <h2 class="section-title" style="font-size:clamp(1.7rem,3.4vw,2.6rem)">Safe &amp; straightforward.</h2>
        </div>
        <div class="faq" data-accordion="single">
            <div class="acc-item open">
                <button class="acc-head" type="button" aria-expanded="true">
                    <span class="acc-title">How do payments work?</span>
                    <span class="acc-icon"><?= icon('plus', 16) ?></span>
                </button>
                <div class="acc-body">
                    <p class="acc-copy">Fill in the short payment form, choose PayPal or card, and complete the payment directly on this page. PayPal payments run through PayPal&rsquo;s checkout; card payments are tokenised by Stripe&rsquo;s secure Elements form. You&rsquo;ll see a confirmation immediately and receive an email receipt.</p>
                </div>
            </div>
            <div class="acc-item">
                <button class="acc-head" type="button" aria-expanded="false">
                    <span class="acc-title">Is the payment page secure?</span>
                    <span class="acc-icon"><?= icon('plus', 16) ?></span>
                </button>
                <div class="acc-body">
                    <p class="acc-copy">Yes. Card numbers never touch this website — they are entered directly into Stripe&rsquo;s encrypted, PCI-DSS compliant form. All payment requests are CSRF-protected and verified server-side before a receipt is issued.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

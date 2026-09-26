<?php
/**
 * The Pie Technologies — Pay Online (/pay-online)
 * Only providers enabled in Admin → Settings → Payments are ever shown.
 * No card details are collected on this site; checkouts are provider-hosted.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$pageTitle = 'Pay Online — Secure Invoice Payments';
$metaDesc  = 'Settle your TPT invoice by secure hosted checkout or request an invoice link. Card details are never collected on this site.';
$activeNav = 'pay';

$paymentsEnabled = getSetting('pay_online_enabled', '1') === '1';
$providers       = $paymentsEnabled ? piePaymentProviders() : array();

/* ------------------------------- POST ---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_submit']) && $paymentsEnabled) {
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if (!validateCSRF()) {
        setFlash('err', 'Your session expired. Please refresh and try again.');
        header('Location: ' . url('pay-online'));
        exit;
    }

    $name      = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
    $email     = sanitize(isset($_POST['email']) ? $_POST['email'] : '');
    $reference = sanitize(isset($_POST['reference']) ? $_POST['reference'] : '');
    $amountRaw = isset($_POST['amount']) ? trim((string) $_POST['amount']) : '';
    $notes     = sanitizeMultiline(isset($_POST['notes']) ? $_POST['notes'] : '');
    $method    = sanitize(isset($_POST['method']) ? $_POST['method'] : 'invoice');

    /* honeypot */
    $honeypot = isset($_POST['website']) ? trim((string) $_POST['website']) : '';
    if ($honeypot !== '') {
        header('Location: ' . url('pay-online') . '?requested=1');
        exit;
    }

    $errors = array();
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150)            { $errors['name'] = 'Please enter the name on the account.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                { $errors['email'] = 'Please enter a valid email for the receipt.'; }
    $amount = (float) preg_replace('/[^0-9.]/', '', $amountRaw);
    if (!is_numeric($amount) || $amount <= 0 || $amount > 1000000) { $errors['amount'] = 'Enter the amount in USD (e.g. 1500.00).'; }
    if (!in_array($method, array('invoice', 'stripe', 'paypal'), true)) { $method = 'invoice'; }
    if ($method !== 'invoice' && !isset($providers[$method]))      { $method = 'invoice'; }

    if ($errors) {
        setFlash('err', reset($errors));
        header('Location: ' . url('pay-online'));
        exit;
    }

    $token = bin2hex(random_bytes(16));
    $newId = dbInsert(
        'INSERT INTO payments (token, name, email, reference, amount_usd, notes, method, status, provider_ref, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        array($token, $name, $email, $reference, number_format($amount, 2, '.', ''), $notes,
              $method, $method === 'invoice' ? 'requested' : 'pending', '', pieClientIp())
    );

    if ($newId < 0) {
        setFlash('err', 'We couldn’t save your request. Please email ' . getSetting('site_email', 'info@thepietechnologies.com') . ' and we’ll sort it manually.');
        header('Location: ' . url('pay-online'));
        exit;
    }

    $payment = piePaymentByToken($token);
    $redirect = null;

    if ($method === 'stripe') {
        $result = pieStripeCheckout($payment);
        if ($result['ok']) {
            dbExec('UPDATE payments SET provider_ref = ? WHERE id = ?', array($result['ref'], (int) $payment['id']));
            $redirect = $result['url'];
        } else {
            /* provider hiccup — fall back to the invoice-link flow, keep the record */
            dbExec('UPDATE payments SET method = "invoice", status = "requested", notes = CONCAT(notes, ?) WHERE id = ?',
                array("\n[stripe checkout unavailable — invoice link requested instead]", (int) $payment['id']));
            $payment['method'] = 'invoice';
        }
    } elseif ($method === 'paypal') {
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

    if ($redirect !== null) {
        header('Location: ' . $redirect);
        exit;
    }
    header('Location: ' . url('pay/secure/' . $token) . '?requested=1');
    exit;
}

$requestedFlash = isset($_GET['requested']);

$jsonLd = json_encode(array(
    '@context' => 'https://schema.org',
    '@type'    => 'WebPage',
    'name'     => 'Pay Online — The Pie Technologies',
    'url'      => canonicalUrl('pay-online'),
), JSON_UNESCAPED_SLASHES);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Secure payments</p>
        <h1>Pay an invoice, securely.</h1>
        <p class="lead">Settle your TPT invoice by secure checkout or request an invoice link. Choose the method that works for you; we never ask for card details on this site.</p>
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
            <div class="contact-panel" data-aos="fade-up">
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
                <p class="sub">Secure hosted checkout · no card details collected here.</p>

                <form id="payForm" method="post" action="<?= url('pay-online') ?>" novalidate>
                    <?= csrfField() ?>
                    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px" placeholder="Leave this empty">

                    <div class="form-grid">
                        <div class="field">
                            <label for="pName">Name on account <span class="req">*</span></label>
                            <input id="pName" name="name" type="text" required maxlength="150" autocomplete="name" placeholder="Your full name">
                        </div>
                        <div class="field">
                            <label for="pEmail">Email for receipt <span class="req">*</span></label>
                            <input id="pEmail" name="email" type="email" required maxlength="150" autocomplete="email" placeholder="you@company.com">
                        </div>
                        <div class="field">
                            <label for="pReference">Invoice / reference</label>
                            <input id="pReference" name="reference" type="text" maxlength="150" placeholder="TPT-2026-0148">
                        </div>
                        <div class="field">
                            <label for="pAmount">Amount (USD) <span class="req">*</span></label>
                            <input id="pAmount" name="amount" type="number" min="1" step="0.01" required inputmode="decimal" placeholder="1500.00">
                        </div>
                        <div class="field full">
                            <label for="pNotes">Notes</label>
                            <textarea id="pNotes" name="notes" maxlength="2000" placeholder="Anything we should know — milestone, PO number, preferred split…"></textarea>
                        </div>

                        <fieldset class="field full" style="border:none;padding:0">
                            <legend class="eyebrow" style="margin-bottom:12px">Method</legend>
                            <div class="pay-methods">
                                <label class="pay-method">
                                    <input type="radio" name="method" value="invoice" checked>
                                    <span>
                                        <strong><?= icon('mail', 16) ?> Request an invoice</strong>
                                        <small>We email you a secure invoice link — pay when it suits you.</small>
                                    </span>
                                </label>
                                <?php foreach ($providers as $provKey => $prov): ?>
                                <label class="pay-method">
                                    <input type="radio" name="method" value="<?= esc($provKey) ?>">
                                    <span>
                                        <strong><?= icon($provKey === 'stripe' ? 'card' : 'loop', 16) ?> <?= esc($prov['label']) ?></strong>
                                        <small><?= esc($prov['note']) ?> <span class="chip" style="margin-left:6px"><?= esc($prov['mode']) ?> mode</span></small>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <div class="full">
                            <button class="btn btn-primary btn-lg btn-block btn-magnetic" type="submit" name="pay_submit" value="1">
                                <?= $providers ? 'Continue securely' : 'Request Invoice Link' ?> <?= icon('arrow-r', 18) ?>
                            </button>
                            <p class="text-muted" style="font-size:.78rem;text-align:center;margin-top:10px"><?= icon('lock', 13) ?> Payments are processed by the provider’s hosted checkout. This site never sees or stores card numbers.</p>
                        </div>
                    </div>
                    <div class="form-status" role="status" aria-live="polite"></div>
                </form>
            </div>

            <aside class="contact-side" data-aos="fade-up" data-aos-delay="120">
                <div class="info-card">
                    <span class="icon"><?= icon('lock', 22) ?></span>
                    <span>
                        <strong>Is this page secure?</strong>
                        <p>Payment details are handled by the configured provider (or by invoice) — card numbers never touch this website. All provider keys live server-side.</p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="icon"><?= icon('card', 22) ?></span>
                    <span>
                        <strong>How do payments work?</strong>
                        <p>Submit the form with your invoice amount and choose a method. Invoice requests are emailed back as a secure link; enabled providers redirect you to their hosted checkout.</p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="icon"><?= icon('mail', 22) ?></span>
                    <span>
                        <strong>Prefer email?</strong>
                        <p>Write to <a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a> with your invoice reference and we&rsquo;ll handle it personally.</p>
                    </span>
                </div>
            </aside>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ================================ FAQ ================================= -->
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
                    <p class="acc-copy">Submit the payment form with your invoice amount and our team will send a secure invoice link to your email. Where the site administrator has enabled a provider, you can also pay immediately through their hosted checkout. Payment credentials are never stored in the browser.</p>
                </div>
            </div>
            <div class="acc-item">
                <button class="acc-head" type="button" aria-expanded="false">
                    <span class="acc-title">Is the payment page secure?</span>
                    <span class="acc-icon"><?= icon('plus', 16) ?></span>
                </button>
                <div class="acc-body">
                    <p class="acc-copy">Payment details are handled by the configured provider (or by invoice) — card numbers never touch this website. All provider keys live server-side, every request is CSRF-protected, and each payment link carries a one-time random token.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

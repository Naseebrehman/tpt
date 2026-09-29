<?php
/**
 * The Pie Technologies — secure payment link (/pay/secure/{token})
 * One-time random token per request. Reconciles with the provider on load,
 * and lets the payer (re)open a hosted checkout when the provider is enabled.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/payments.php';

$token = isset($_GET['token']) ? preg_replace('/[^a-f0-9]/', '', (string) $_GET['token']) : '';
$payment = $token !== '' ? piePaymentByToken($token) : null;

if (!$payment) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$payment = piePaymentReconcile($payment);
$providers = piePaymentProviders();

/* Re-open a hosted checkout from the secure link (provider must be enabled). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now']) && $payment['status'] !== 'paid') {
    if (!validateCSRF()) {
        setFlash('err', 'Your session expired. Please try again.');
        header('Location: ' . url('pay/secure/' . $token));
        exit;
    }
    $method = sanitize(isset($_POST['method']) ? $_POST['method'] : '');
    if (!isset($providers[$method])) {
        setFlash('err', 'That payment method is not available right now.');
        header('Location: ' . url('pay/secure/' . $token));
        exit;
    }
    $payment['method'] = $method;
    dbExec('UPDATE payments SET method = ?, status = "pending" WHERE id = ?', array($method, (int) $payment['id']));
    $result = $method === 'stripe' ? pieStripeCheckout($payment) : piePayPalOrder($payment);
    if ($result['ok']) {
        dbExec('UPDATE payments SET provider_ref = ? WHERE id = ?', array($result['ref'], (int) $payment['id']));
        header('Location: ' . $result['url']);
        exit;
    }
    dbExec('UPDATE payments SET status = "failed" WHERE id = ?', array((int) $payment['id']));
    setFlash('err', 'The provider could not start the checkout just now. Your request is saved — our team will email you a working link.');
    header('Location: ' . url('pay/secure/' . $token));
    exit;
}

$statusLabels = array(
    'requested' => 'Invoice link requested',
    'pending'   => 'Awaiting payment',
    'paid'      => 'Paid',
    'failed'    => 'Needs attention',
    'cancelled' => 'Cancelled',
);
$statusLabel = isset($statusLabels[$payment['status']]) ? $statusLabels[$payment['status']] : ucfirst($payment['status']);

$pageTitle = 'Secure payment — ' . ($payment['reference'] !== '' ? $payment['reference'] : substr($token, 0, 8));
$metaDesc  = 'Your secure TPT payment link.';
$activeNav = 'pay';
$noIndex   = true;

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; <a href="<?= url('pay-online') ?>">Pay Online</a> &nbsp;/&nbsp; Secure link</p>
        <h1 style="font-size:clamp(1.9rem,4vw,3rem)">Your secure payment link.</h1>
        <span class="pay-status-badge <?= esc($payment['status']) ?>" style="margin-top:14px"><?= esc($statusLabel) ?></span>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:760px">
        <?php $secureFlash = getFlash(); if ($secureFlash): ?>
        <div class="chart-card" style="margin-bottom:18px;border-color:rgba(248,113,113,.4)" data-aos="fade-up">
            <p style="color:#f87171"><?= esc($secureFlash['message']) ?></p>
        </div>
        <?php endif; ?>

        <div class="chart-card" data-aos="fade-up">
            <p class="eyebrow" style="margin-bottom:16px">Payment summary</p>
            <div style="display:grid;gap:12px">
                <div style="display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
                    <span class="text-muted">Amount</span>
                    <strong style="font-size:1.3rem;color:#fff">$<?= esc(number_format((float) $payment['amount_usd'], 2)) ?> <span class="text-muted" style="font-size:.8rem;font-weight:400">USD</span></strong>
                </div>
                <div style="display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
                    <span class="text-muted">Name on account</span><span><?= esc($payment['name']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
                    <span class="text-muted">Receipt email</span><span><?= esc($payment['email']) ?></span>
                </div>
                <?php if (!empty($payment['phone'])): ?>
                <div style="display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
                    <span class="text-muted">Phone</span><span><?= esc($payment['phone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($payment['service'])): ?>
                <div style="display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
                    <span class="text-muted">Service</span><span><?= esc($payment['service']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($payment['reference'] !== ''): ?>
                <div style="display:flex;justify-content:space-between;gap:16px;border-bottom:1px solid var(--line);padding-bottom:12px">
                    <span class="text-muted">Invoice / reference</span><span class="mono"><?= esc($payment['reference']) ?></span>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;gap:16px">
                    <span class="text-muted">Requested</span><span><?= esc(formatDate($payment['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <?php if ($payment['status'] === 'paid'): ?>
        <div class="chart-card" style="margin-top:18px;text-align:center;border-color:rgba(52,211,153,.4)" data-aos="fade-up">
            <span style="color:#34d399;display:inline-flex;margin-bottom:10px"><?= icon('check', 40) ?></span>
            <h2 class="pay-state-title" style="font-size:1.4rem;margin-bottom:8px">Payment Successful</h2>
            <p class="text-muted">Thank you, <?= esc($payment['name']) ?> — we received <strong style="color:#fff">$<?= esc(number_format((float) $payment['amount_usd'], 2)) ?></strong><?php if (!empty($payment['service'])): ?> for <?= esc($payment['service']) ?><?php endif; ?>. A receipt is on its way to <?= esc($payment['email']) ?>. If anything looks off, reply to that email and a human will sort it.</p>
            <?php if ($payment['provider_ref'] !== ''): ?>
            <p class="text-muted" style="font-size:.85rem;margin-top:8px">Transaction ID: <span class="mono"><?= esc($payment['provider_ref']) ?></span> · Method: <?= esc(piePaymentMethodLabel($payment['method'])) ?> · <?= esc(formatDate($payment['created_at'])) ?></p>
            <?php endif; ?>
        </div>
        <?php elseif (isset($_GET['paid'])): ?>
        <div class="chart-card" style="margin-top:18px;text-align:center" data-aos="fade-up">
            <h2 class="pay-state-title" style="font-size:1.4rem;margin-bottom:8px">Finalizing your payment…</h2>
            <p class="text-muted">We&rsquo;re confirming the transaction with the provider. This page updates automatically — refresh in a moment if it doesn&rsquo;t.</p>
        </div>
        <?php elseif ($payment['status'] === 'failed'): ?>
        <div class="chart-card" style="margin-top:18px;text-align:center;border-color:rgba(248,113,113,.4)" data-aos="fade-up">
            <h2 class="pay-state-title" style="font-size:1.4rem;margin-bottom:8px">Payment Could Not Be Completed</h2>
            <p class="text-muted">No charge was made. You can try again below — if it keeps failing, reply to your confirmation email and we&rsquo;ll help personally.</p>
        </div>
        <?php elseif (isset($_GET['cancelled'])): ?>
        <div class="chart-card" style="margin-top:18px" data-aos="fade-up">
            <p><strong style="color:#fbbf24">Checkout cancelled.</strong> <span class="text-muted">Nothing was charged. You can reopen the checkout below, or reply to your confirmation email and we&rsquo;ll help personally.</span></p>
        </div>
        <?php endif; ?>

        <?php if ($payment['status'] !== 'paid'): ?>
        <div class="chart-card" style="margin-top:18px" data-aos="fade-up">
            <p class="eyebrow" style="margin-bottom:14px">Pay now</p>
            <?php if ($providers): ?>
            <p class="text-muted" style="font-size:.9rem;margin-bottom:16px">Choose a provider — you&rsquo;ll complete the payment on their hosted, encrypted checkout and land back here.</p>
            <?php foreach ($providers as $provKey => $prov): ?>
            <form method="post" action="<?= url('pay/secure/' . $token) ?>" style="margin-bottom:10px">
                <?= csrfField() ?>
                <input type="hidden" name="method" value="<?= esc($provKey) ?>">
                <button class="btn <?= $provKey === 'stripe' ? 'btn-primary' : 'btn-ghost' ?> btn-block" type="submit" name="pay_now" value="1">
                    <?= icon($provKey === 'stripe' ? 'card' : 'loop', 17) ?> Pay $<?= esc(number_format((float) $payment['amount_usd'], 2)) ?> with <?= $provKey === 'stripe' ? 'card (Stripe)' : 'PayPal' ?> — <?= esc($prov['mode']) ?> mode
                </button>
            </form>
            <?php endforeach; ?>
            <?php else: ?>
            <p class="text-muted" style="font-size:.92rem;line-height:1.75">Your request is with our team — a secure invoice link or payment instructions will arrive at <strong style="color:var(--text)"><?= esc($payment['email']) ?></strong> within one business day. This page stays live; when the team enables a provider or marks the invoice paid, it updates here.</p>
            <?php endif; ?>
            <p class="text-muted" style="font-size:.78rem;margin-top:14px"><?= icon('lock', 13) ?> Card details are entered on the provider’s site, never here. This link carries a one-time token tied to your request.</p>
        </div>
        <?php endif; ?>

        <p class="text-muted" style="text-align:center;margin-top:26px;font-size:.88rem" data-aos="fade-up">Questions about this invoice? Email <a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>" style="color:var(--violet-soft)"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a> or ask <strong style="color:var(--text)">Alia</strong> in the corner.</p>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

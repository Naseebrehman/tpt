<?php
class StripeWebhook
{
    public static function validSignature($payload, $header, $secret, $now = null)
    {
        if ($secret === '' || strlen($header) > 4096) { return false; }
        $timestamp = null; $signatures = array();
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) { continue; }
            if ($pair[0] === 't' && ctype_digit($pair[1])) { $timestamp = (int) $pair[1]; }
            if ($pair[0] === 'v1') { $signatures[] = $pair[1]; }
        }
        if (!$timestamp || abs(($now ?? time()) - $timestamp) > 300) { return false; }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $signature) { if (hash_equals($expected, $signature)) { return true; } }
        return false;
    }
    public static function handle()
    {
        $raw = file_get_contents('php://input', false, null, 0, 262145);
        if (strlen($raw) > 262144 || !self::validSignature($raw, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', getSetting('stripe_webhook_secret'))) {
            ApiController::json(array('success' => false), 400); return;
        }
        $event = json_decode($raw, true);
        if (!is_array($event) || empty($event['id']) || !is_string($event['id']) || strlen($event['id']) > 255) { ApiController::json(array('success' => false), 400); return; }
        $type = $event['type'] ?? '';
        $isCheckout = in_array($type, array('checkout.session.completed', 'checkout.session.async_payment_succeeded'), true);
        $isIntent   = $type === 'payment_intent.succeeded';
        if (!$isCheckout && !$isIntent) { ApiController::json(array('received' => true)); return; }
        if (!DB_OK) { ApiController::json(array('success' => false), 503); return; }
        $object = $event['data']['object'] ?? array();
        if ($isCheckout && ($object['payment_status'] ?? '') !== 'paid') { ApiController::json(array('received' => true)); return; }
        $pdo = $GLOBALS['pdo'];
        $paidPayment = null;
        try {
            $pdo->beginTransaction();
            if ($isCheckout) {
                $check = $pdo->prepare('SELECT * FROM payments WHERE provider_ref = ? AND method = ? FOR UPDATE');
                $check->execute(array($object['id'] ?? '', 'stripe')); $payment = $check->fetch();
                if (!$payment || ($object['client_reference_id'] ?? '') !== $payment['token'] || strtolower($object['currency'] ?? '') !== 'usd' || (int) ($object['amount_total'] ?? -1) !== (int) round((float) $payment['amount_usd'] * 100)) {
                    $pdo->rollBack(); ApiController::json(array('success' => false), 409); return;
                }
            } else {
                /* Inline Elements PaymentIntent — match on the intent id or the token metadata. */
                $intentId = $object['id'] ?? '';
                $check = $pdo->prepare('SELECT * FROM payments WHERE (provider_ref = ? OR token = ?) AND method = ? FOR UPDATE');
                $check->execute(array($intentId, ($object['metadata']['token'] ?? ''), 'stripe')); $payment = $check->fetch();
                if (!$payment || strtolower($object['currency'] ?? '') !== 'usd' || (int) ($object['amount_received'] ?? $object['amount'] ?? -1) !== (int) round((float) $payment['amount_usd'] * 100)) {
                    $pdo->rollBack(); ApiController::json(array('success' => false), 409); return;
                }
            }
            $seen = $pdo->prepare('SELECT event_id FROM payment_events WHERE event_id = ?'); $seen->execute(array($event['id']));
            if (!$seen->fetchColumn()) {
                $insert = $pdo->prepare('INSERT INTO payment_events (event_id, payment_id, event_type) VALUES (?, ?, ?)');
                $insert->execute(array($event['id'], $payment['id'], $type));
                $update = $pdo->prepare("UPDATE payments SET status = 'paid' WHERE id = ? AND status <> 'paid'"); $update->execute(array($payment['id']));
                if ($update->rowCount() === 1) { $paidPayment = $payment; }
            }
            $pdo->commit();
            if ($paidPayment) {
                /* One-time customer + admin notices after the commit (Task 6). */
                require_once dirname(__DIR__) . '/core/Payments.php';
                $paidPayment['status'] = 'paid';
                piePaymentSendPaidNotices($paidPayment);
            }
            ApiController::json(array('received' => true));
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[TPT] Stripe webhook persistence failed; check migrations.');
            ApiController::json(array('success' => false), 503); // Stripe retries failed persistence.
        }
    }
}

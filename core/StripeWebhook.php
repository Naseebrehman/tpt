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
        if (!in_array($event['type'] ?? '', array('checkout.session.completed', 'checkout.session.async_payment_succeeded'), true)) { ApiController::json(array('received' => true)); return; }
        if (!DB_OK) { ApiController::json(array('success' => false), 503); return; }
        $session = $event['data']['object'] ?? array();
        if (($session['payment_status'] ?? '') !== 'paid') { ApiController::json(array('received' => true)); return; }
        $pdo = $GLOBALS['pdo'];
        try {
            $pdo->beginTransaction();
            $check = $pdo->prepare('SELECT * FROM payments WHERE provider_ref = ? AND method = ? FOR UPDATE');
            $check->execute(array($session['id'] ?? '', 'stripe')); $payment = $check->fetch();
            if (!$payment || ($session['client_reference_id'] ?? '') !== $payment['token'] || strtolower($session['currency'] ?? '') !== 'usd' || (int) ($session['amount_total'] ?? -1) !== (int) round((float) $payment['amount_usd'] * 100)) {
                $pdo->rollBack(); ApiController::json(array('success' => false), 409); return;
            }
            $seen = $pdo->prepare('SELECT event_id FROM payment_events WHERE event_id = ?'); $seen->execute(array($event['id']));
            if (!$seen->fetchColumn()) {
                $insert = $pdo->prepare('INSERT INTO payment_events (event_id, payment_id, event_type) VALUES (?, ?, ?)');
                $insert->execute(array($event['id'], $payment['id'], $event['type']));
                $update = $pdo->prepare("UPDATE payments SET status = 'paid' WHERE id = ?"); $update->execute(array($payment['id']));
            }
            $pdo->commit();
            ApiController::json(array('received' => true));
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[TPT] Stripe webhook persistence failed; check migrations.');
            ApiController::json(array('success' => false), 503); // Stripe retries failed persistence.
        }
    }
}

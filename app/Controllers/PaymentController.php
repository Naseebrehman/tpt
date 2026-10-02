<?php
require_once BASE_PATH . '/core/Payments.php';

/** Server endpoints for PayPal/Stripe hosted checkout creation and webhooks. */
class PaymentController
{
    private static function respond(array $payload, $status = 200)
    {
        http_response_code((int) $status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /** Create a provider checkout only after CSRF and server-side validation. */
    public static function create($provider)
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::respond(array('success' => false, 'message' => 'Use POST to start checkout.'), 405);
            return;
        }
        if (!validateCSRF()) {
            self::respond(array('success' => false, 'message' => 'Your session expired. Refresh the page and try again.'), 403);
            return;
        }
        $provider = strtolower(trim((string) $provider));
        if (!in_array($provider, array('paypal', 'stripe'), true)) {
            self::respond(array('success' => false, 'message' => 'Choose a supported payment method.'), 404);
            return;
        }
        if (($provider === 'paypal' && !piePayPalConfigured()) || ($provider === 'stripe' && !pieStripeConfigured())) {
            self::respond(array('success' => false, 'message' => 'This payment method is temporarily unavailable. Please contact our team.'), 503);
            return;
        }

        $name = sanitize($_POST['name'] ?? '');
        $service = sanitize($_POST['service'] ?? '');
        $amount = PaymentGateway::normalizeAmount($_POST['amount'] ?? '');
        $errors = array();
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150) { $errors['name'] = 'Please enter a valid name or business name.'; }
        if (!in_array($service, piePaymentServices(), true)) { $errors['service'] = 'Please choose a service from the list.'; }
        if ($amount === false) { $errors['amount'] = 'Please enter a valid amount in USD (0.01 to 1,000,000.00).'; }
        if ($errors) {
            self::respond(array('success' => false, 'message' => reset($errors), 'errors' => $errors), 422);
            return;
        }
        if (!DB_OK) {
            self::respond(array('success' => false, 'message' => 'Online payments are temporarily unavailable. Please contact our team.'), 503);
            return;
        }

        try {
            $checkout = PaymentGateway::createCheckout($provider, array('name' => $name, 'service' => $service, 'amount' => $amount['decimal']));
            self::respond(array('success' => true, 'message' => 'Checkout is ready.', 'redirect_url' => $checkout['redirect_url'],
                'payment_id' => $checkout['payment_id'], 'state' => $checkout['state']));
        } catch (Throwable $error) {
            /* Provider response bodies and credentials are never returned. */
            error_log('[TPT] ' . ucfirst($provider) . ' checkout initialization failed.');
            self::respond(array('success' => false, 'message' => 'We could not start checkout. Your form details have been kept. Please try again or contact us.'), 502);
        }
    }

    /** Stripe webhook endpoint: authenticity is checked using Stripe-Signature. */
    public static function stripeWebhook()
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::respond(array('success' => false), 405);
            return;
        }
        if (!defined('STRIPE_WEBHOOK_SECRET') || trim((string) STRIPE_WEBHOOK_SECRET) === '' || !pieStripeConfigured()) {
            self::respond(array('success' => false), 503);
            return;
        }
        $payload = file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        if (!PaymentGateway::handleStripeWebhook((string) $payload, $signature)) {
            self::respond(array('success' => false), 400);
            return;
        }
        self::respond(array('success' => true));
    }
}

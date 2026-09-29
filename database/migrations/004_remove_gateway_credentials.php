<?php
/** Clear gateway credentials and embedded SDK snippets from older installs.
 * Checkout is now owned entirely by client-side code maintained by the site owner. */
return function (PDO $pdo) {
    $stmt = $pdo->prepare("DELETE FROM settings WHERE setting_key IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(array(
        'paypal_client_id', 'paypal_secret', 'paypal_mode', 'paypal_enabled', 'paypal_sdk_code',
        'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret', 'stripe_mode', 'stripe_enabled', 'stripe_sdk_code',
    ));
};

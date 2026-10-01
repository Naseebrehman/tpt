<?php
/** Server-only encryption for gateway credentials stored in settings.
 *
 * Ciphertext format: enc:v1:<base64(iv || tag || ciphertext)> using AES-256-GCM.
 * A dedicated PAYMENT_ENCRYPTION_KEY/TPT_PAYMENT_ENCRYPTION_KEY is preferred.
 * For deployments that have not set one, a key is derived from the existing
 * private database credentials and site URL so upgrading does not require an
 * additional secret to be exposed or stored in the database.
 */

function piePaymentCredentialIsEncrypted($value)
{
    return is_string($value) && strpos($value, 'enc:v1:') === 0;
}

/** A stable 32-byte key that never comes from the settings table. */
function piePaymentCredentialKey()
{
    $configured = defined('PAYMENT_ENCRYPTION_KEY') ? (string) PAYMENT_ENCRYPTION_KEY : '';
    if ($configured === '') {
        $configured = getenv('TPT_PAYMENT_ENCRYPTION_KEY') ?: '';
    }
    if ($configured === '') {
        $configured = implode("\0", array(
            defined('DB_HOST') ? DB_HOST : '',
            defined('DB_NAME') ? DB_NAME : '',
            defined('DB_USER') ? DB_USER : '',
            defined('DB_PASS') ? DB_PASS : '',
            defined('SITE_URL') ? SITE_URL : '',
            'tpt-payment-credentials-v1',
        ));
    }
    return hash('sha256', $configured, true);
}

/** Encrypt a credential for database storage. Returns false when unavailable. */
function piePaymentCredentialEncrypt($plaintext)
{
    $plaintext = (string) $plaintext;
    if ($plaintext === '') {
        return '';
    }
    if (!function_exists('openssl_encrypt') || !function_exists('random_bytes')) {
        return false;
    }
    try {
        $iv = random_bytes(12);
    } catch (Throwable $error) {
        return false;
    }
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        'aes-256-gcm',
        piePaymentCredentialKey(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'tpt-payment-credential:v1',
        16
    );
    if (!is_string($ciphertext) || strlen($tag) !== 16) {
        return false;
    }
    return 'enc:v1:' . base64_encode($iv . $tag . $ciphertext);
}

/** Decrypt one setting value. Unprefixed values support migration from legacy rows. */
function piePaymentCredentialDecrypt($storedValue)
{
    $storedValue = (string) $storedValue;
    if (!piePaymentCredentialIsEncrypted($storedValue)) {
        return $storedValue;
    }
    if (!function_exists('openssl_decrypt')) {
        return '';
    }
    $packed = base64_decode(substr($storedValue, 7), true);
    if (!is_string($packed) || strlen($packed) < 29) {
        return '';
    }
    $iv = substr($packed, 0, 12);
    $tag = substr($packed, 12, 16);
    $ciphertext = substr($packed, 28);
    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        piePaymentCredentialKey(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        'tpt-payment-credential:v1'
    );
    return is_string($plaintext) ? $plaintext : '';
}

/** Read a gateway credential and decrypt it only inside the PHP process. */
function piePaymentCredential($settingKey)
{
    if (!function_exists('getSetting')) {
        return '';
    }
    return trim(piePaymentCredentialDecrypt((string) getSetting((string) $settingKey, '')));
}

/** Store an encrypted gateway credential using the shared settings table. */
function pieSavePaymentCredential($settingKey, $plaintext)
{
    if (!function_exists('dbExec')) {
        return -1;
    }
    $encrypted = piePaymentCredentialEncrypt((string) $plaintext);
    if (!is_string($encrypted) || $encrypted === '') {
        return -1;
    }
    return dbExec(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        array((string) $settingKey, $encrypted)
    );
}

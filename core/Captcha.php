<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — CAPTCHA / anti-bot verification (Task 20)
 * ---------------------------------------------------------------------------
 *  Supports Cloudflare Turnstile (low friction), hCaptcha and reCAPTCHA v2.
 *  The secret key is used only here, server-side — never printed into HTML,
 *  JavaScript or API responses. Verification happens on the server for every
 *  protected form submission.
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

class Captcha
{
    public static function providers()
    {
        return array(
            'turnstile' => 'Cloudflare Turnstile (recommended — low friction)',
            'hcaptcha'  => 'hCaptcha',
            'recaptcha' => 'Google reCAPTCHA v2',
        );
    }

    /** Configured and enabled? */
    public static function enabled()
    {
        if (getSetting('captcha_enabled', '0') !== '1') {
            return false;
        }
        $provider = getSetting('captcha_provider', 'turnstile');
        return isset(self::providers()[$provider])
            && getSetting('captcha_site_key', '') !== ''
            && getSetting('captcha_secret_key', '') !== '';
    }

    /** Public site key (safe for HTML). */
    public static function siteKey()
    {
        return getSetting('captcha_site_key', '');
    }

    public static function provider()
    {
        $provider = getSetting('captcha_provider', 'turnstile');
        return isset(self::providers()[$provider]) ? $provider : 'turnstile';
    }

    /** Render the widget container + provider script (idempotent). */
    public static function field()
    {
        if (!self::enabled()) {
            return '';
        }
        $provider = self::provider();
        $siteKey  = esc(self::siteKey());
        if ($provider === 'turnstile') {
            $html = '<div class="captcha-field"><div class="cf-turnstile" data-sitekey="' . $siteKey . '" data-size="flexible" data-theme="dark" data-action="tpt_form"></div></div>';
        } elseif ($provider === 'hcaptcha') {
            $html = '<div class="captcha-field"><div class="h-captcha" data-sitekey="' . $siteKey . '" data-theme="dark"></div></div>';
        } else {
            $html = '<div class="captcha-field"><div class="g-recaptcha" data-sitekey="' . $siteKey . '" data-theme="dark"></div></div>';
        }
        $html .= self::scriptTag();
        return $html;
    }

    /** Provider loader script — output once per page. */
    private static function scriptTag()
    {
        static $printed = false;
        if ($printed) {
            return '';
        }
        $printed = true;
        $provider = self::provider();
        if ($provider === 'turnstile') {
            return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>';
        }
        if ($provider === 'hcaptcha') {
            return '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
        }
        return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    }

    /**
     * Collect the response token from a submitted form (widget field names
     * differ per provider; our JS may also post an explicit captcha_token).
     */
    public static function tokenFromRequest()
    {
        foreach (array('captcha_token', 'cf-turnstile-response', 'h-captcha-response', 'g-recaptcha-response') as $field) {
            if (isset($_POST[$field]) && is_string($_POST[$field]) && $_POST[$field] !== '') {
                return $_POST[$field];
            }
        }
        return '';
    }

    /**
     * Verify a submitted token server-side (Task 20/22: never trust the client).
     * Returns true when CAPTCHA is disabled (not required) or verification passes.
     *
     * @param string $token  response token from the widget
     * @param string $remote client IP passed to the provider for extra checks
     */
    public static function verify($token, $remote = '')
    {
        if (!self::enabled()) {
            return true;
        }
        $token = trim((string) $token);
        if ($token === '' || strlen($token) > 4096) {
            return false;
        }
        $provider = self::provider();
        $urls = array(
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'hcaptcha'  => 'https://hcaptcha.com/siteverify',
            'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
        );
        $fields = array('secret' => getSetting('captcha_secret_key', ''), 'response' => $token);
        if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP)) {
            $fields['remoteip'] = $remote;
        }
        $result = self::post($urls[$provider], $fields);
        if ($result === null) {
            error_log('[TPT] CAPTCHA verification unreachable — rejecting submission for safety.');
            return false;
        }
        return !empty($result['success']);
    }

    /** Provider siteverify call (cURL with stream fallback). */
    private static function post($url, array $fields)
    {
        $body = http_build_query($fields);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ));
            $response = curl_exec($ch);
            curl_close($ch);
            if ($response === false) {
                return null;
            }
            return json_decode($response, true);
        }
        $context = stream_context_create(array(
            'http' => array(
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $body,
                'timeout'       => 10,
                'ignore_errors' => true,
            ),
        ));
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }
        return json_decode($response, true);
    }
}

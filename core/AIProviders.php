<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — chatbot AI providers (Tasks 13–16)
 * ---------------------------------------------------------------------------
 *  Two configurable provider slots; exactly one is active at a time
 *  (the admin picks it — visitors never choose). API keys live only in the
 *  settings table and are used server-side. Every failure mode (rate limits,
 *  timeouts, invalid keys, unavailable models, provider outages) degrades to
 *  a professional fallback message instead of breaking the chat UI.
 *
 *  Provider types:
 *    gemini — Google Generative Language (free tier available)
 *    openai — OpenAI-compatible chat completions (OpenAI, OpenRouter, Groq,
 *             DeepSeek, Together … several offer free tiers)
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

class AIProviders
{
    /** Built-in provider types. */
    public static function types()
    {
        return array(
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI-compatible (OpenAI, OpenRouter, Groq, DeepSeek …)',
        );
    }

    /**
     * Configuration for one provider slot (1 or 2), with legacy key fallbacks
     * so existing Gemini settings keep working.
     */
    public static function config($slot)
    {
        $slot  = (int) $slot === 2 ? 2 : 1;
        $pre   = 'ai_provider' . $slot . '_';
        $type  = getSetting($pre . 'type', $slot === 1 ? 'gemini' : 'openai');
        if (!isset(self::types()[$type])) {
            $type = $slot === 1 ? 'gemini' : 'openai';
        }
        $apiKey = getSetting($pre . 'api_key', '');
        $model  = getSetting($pre . 'model', '');
        if ($slot === 1) {
            /* Legacy settings keep working unchanged. */
            if ($apiKey === '') { $apiKey = getSetting('gemini_api_key', ''); }
            if ($model === '')  { $model  = getSetting('gemini_model', 'gemini-2.5-flash'); }
        }
        return array(
            'slot'        => $slot,
            'enabled'     => getSetting($pre . 'enabled', $slot === 1 ? '1' : '0') === '1',
            'type'        => $type,
            'api_key'     => $apiKey,
            'model'       => $model,
            'base_url'    => rtrim(getSetting($pre . 'base_url', $type === 'openai' ? 'https://api.openai.com/v1' : ''), '/'),
        );
    }

    /** The active provider slot (1 or 2). */
    public static function activeSlot()
    {
        return getSetting('ai_active_provider', '1') === '2' ? 2 : 1;
    }

    /** Active provider config. */
    public static function active()
    {
        return self::config(self::activeSlot());
    }

    /** Shared generation settings (legacy Gemini keys still honoured). */
    public static function generation()
    {
        $maxTokens   = (int) getSetting('chatbot_max_tokens', getSetting('gemini_max_tokens', '300'));
        $temperature = (float) getSetting('chatbot_temperature', getSetting('gemini_temperature', '0.7'));
        return array(
            'max_tokens'   => max(32, min(8192, $maxTokens)),
            'temperature' => max(0, min(2, $temperature)),
        );
    }

    /**
     * Send a chat turn to the active provider.
     *
     * @param array  $history  [['role'=>'user'|'assistant','content'=>'…'], …]
     * @param string $system   system prompt
     * @return array{ok:bool,reply:string,error:string,detail:string}
     *         error is one of: '', config, timeout, auth, rate_limit, model, provider, empty
     *         detail is for server logs only — never shown to visitors.
     */
    public static function chat(array $history, $system = '')
    {
        $cfg = self::active();
        if (!$cfg['enabled']) {
            return self::fail('config', 'Chatbot provider ' . $cfg['slot'] . ' is disabled.');
        }
        if ($cfg['api_key'] === '') {
            return self::fail('config', 'No API key configured for provider ' . $cfg['slot'] . '.');
        }
        if ($cfg['model'] === '' || !preg_match('/^[a-zA-Z0-9._\/:-]{1,120}$/D', $cfg['model'])) {
            return self::fail('model', 'Invalid or missing model for provider ' . $cfg['slot'] . '.');
        }
        $gen = self::generation();
        return $cfg['type'] === 'gemini'
            ? self::callGemini($cfg, $history, $system, $gen)
            : self::callOpenAI($cfg, $history, $system, $gen);
    }

    /* ------------------------------------------------------------------
       Provider transports
       ------------------------------------------------------------------ */

    /** Google Generative Language API. */
    public static function callGemini($cfg, array $history, $system, array $gen)
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($cfg['model']) . ':generateContent';
        $contents = array();
        foreach ($history as $turn) {
            if (!is_array($turn)) { continue; }
            $role = isset($turn['role']) && ($turn['role'] === 'assistant' || $turn['role'] === 'model') ? 'model' : 'user';
            $text = isset($turn['content']) ? mb_substr(trim((string) $turn['content']), 0, 4000) : '';
            if ($text !== '') {
                $contents[] = array('role' => $role, 'parts' => array(array('text' => $text)));
            }
        }
        if (!$contents) {
            return self::fail('empty', 'No conversation content.');
        }
        $payload = array('contents' => $contents);
        if (trim((string) $system) !== '') {
            $payload['system_instruction'] = array('parts' => array(array('text' => mb_substr($system, 0, 8000))));
        }
        $payload['generationConfig'] = array('maxOutputTokens' => $gen['max_tokens'], 'temperature' => $gen['temperature']);

        $response = self::request($url, array('Content-Type: application/json', 'x-goog-api-key: ' . $cfg['api_key']), json_encode($payload));
        if ($response['http'] === 0) {
            return self::fail('timeout', 'Gemini unreachable: ' . $response['error']);
        }
        $data = json_decode($response['body'], true);
        if (is_array($data) && isset($data['candidates'][0]['content']['parts'])) {
            $reply = '';
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) { $reply .= $part['text']; }
            }
            if (trim($reply) !== '') {
                return array('ok' => true, 'reply' => trim($reply), 'error' => '', 'detail' => '');
            }
        }
        return self::fail(self::classify($response['http'], $data), 'Gemini error: ' . self::providerMessage($data, $response));
    }

    /** OpenAI-compatible /chat/completions. */
    public static function callOpenAI($cfg, array $history, $system, array $gen)
    {
        $messages = array();
        if (trim((string) $system) !== '') {
            $messages[] = array('role' => 'system', 'content' => mb_substr($system, 0, 8000));
        }
        $userTurns = 0;
        foreach ($history as $turn) {
            if (!is_array($turn)) { continue; }
            $role = isset($turn['role']) && ($turn['role'] === 'assistant' || $turn['role'] === 'model') ? 'assistant' : 'user';
            $text = isset($turn['content']) ? mb_substr(trim((string) $turn['content']), 0, 4000) : '';
            if ($text !== '') {
                $messages[] = array('role' => $role, 'content' => $text);
                if ($role === 'user') { $userTurns++; }
            }
        }
        if ($userTurns < 1) {
            return self::fail('empty', 'No conversation content.');
        }
        $url  = ($cfg['base_url'] !== '' ? $cfg['base_url'] : 'https://api.openai.com/v1') . '/chat/completions';
        $body = json_encode(array(
            'model'       => $cfg['model'],
            'messages'    => $messages,
            'max_tokens'  => $gen['max_tokens'],
            'temperature' => $gen['temperature'],
        ));
        $response = self::request($url, array('Content-Type: application/json', 'Authorization: Bearer ' . $cfg['api_key']), $body);
        if ($response['http'] === 0) {
            return self::fail('timeout', 'Provider unreachable: ' . $response['error']);
        }
        $data = json_decode($response['body'], true);
        if (is_array($data) && isset($data['choices'][0]['message']['content'])) {
            $reply = trim((string) $data['choices'][0]['message']['content']);
            if ($reply !== '') {
                return array('ok' => true, 'reply' => $reply, 'error' => '', 'detail' => '');
            }
        }
        return self::fail(self::classify($response['http'], $data), 'Provider error: ' . self::providerMessage($data, $response));
    }

    /* ------------------------------------------------------------------
       Internals
       ------------------------------------------------------------------ */

    private static function fail($error, $detail)
    {
        error_log('[TPT] AI provider: ' . $detail);
        return array('ok' => false, 'reply' => '', 'error' => $error, 'detail' => $detail);
    }

    private static function classify($http, $data)
    {
        if ($http === 401 || $http === 403) { return 'auth'; }
        if ($http === 429) { return 'rate_limit'; }
        if ($http === 404) { return 'model'; }
        if ($http >= 500) { return 'provider'; }
        if (is_array($data) && isset($data['error']['message'])) {
            $message = strtolower($data['error']['message']);
            if (strpos($message, 'rate') !== false || strpos($message, 'quota') !== false) { return 'rate_limit'; }
            if (strpos($message, 'api key') !== false || strpos($message, 'unauth') !== false) { return 'auth'; }
        }
        return 'provider';
    }

    private static function providerMessage($data, $response)
    {
        if (is_array($data) && isset($data['error']['message'])) {
            return (string) $data['error']['message'];
        }
        return 'HTTP ' . $response['http'] . ' ' . mb_substr((string) $response['body'], 0, 200);
    }

    /** POST JSON with cURL (stream fallback). Returns array(http:int, body:string, error:string). */
    private static function request($url, array $headers, $body)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 35,
                CURLOPT_CONNECTTIMEOUT => 10,
            ));
            $response = curl_exec($ch);
            $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                return array('http' => 0, 'body' => '', 'error' => $error !== '' ? $error : 'connection failed');
            }
            return array('http' => $http, 'body' => (string) $response, 'error' => '');
        }
        $context = stream_context_create(array(
            'http' => array(
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 35,
                'ignore_errors' => true,
            ),
        ));
        /* fopen() + stream_get_meta_data() is used instead of
           file_get_contents() so the status code does not depend on the
           magic $http_response_header variable (deprecated in PHP 8.5). */
        $handle = @fopen($url, 'rb', false, $context);
        if ($handle === false) {
            return array('http' => 0, 'body' => '', 'error' => 'connection failed');
        }
        $response = stream_get_contents($handle);
        $meta     = stream_get_meta_data($handle);
        fclose($handle);
        if ($response === false) {
            return array('http' => 0, 'body' => '', 'error' => 'connection failed');
        }
        $status = 0;
        $wrapperData = isset($meta['wrapper_data']) && is_array($meta['wrapper_data']) ? $meta['wrapper_data'] : array();
        if (isset($wrapperData[0]) && preg_match('/\s(\d{3})\s/', (string) $wrapperData[0] . ' ', $m)) {
            $status = (int) $m[1];
        }
        return array('http' => $status, 'body' => (string) $response, 'error' => '');
    }
}

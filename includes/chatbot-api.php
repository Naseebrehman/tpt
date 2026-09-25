<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — PIE Bot backend (Gemini powered)
 * ---------------------------------------------------------------------------
 *  Accepts POST JSON: {message, history[], session_id, name, email}
 *  Reads the Gemini API key + editable system prompt from the settings
 *  table, calls gemini-1.5-flash, stores chatbot leads and returns JSON:
 *  {success:bool, reply:string}
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once __DIR__ . '/init.php';
}

function chatbotDefaultPrompt()
{
    return "You are PIE Bot, the friendly and professional assistant for The Pie Technologies, a full-service digital marketing agency. You help visitors understand our services: Meta Ads (Facebook & Instagram advertising), Social Media Management, SEO, Web Development, Email Marketing, Google Ads, and Branding & Design. Be concise, helpful and professional. When appropriate, encourage visitors to fill out the contact form or book a free consultation. If asked about pricing, say packages are customized per client and suggest they get in touch for a free quote. Always stay on topic about digital marketing and our agency services. If asked something unrelated, politely redirect.";
}

/** POST the payload to Gemini and return the decoded JSON (or null). */
function chatbotCallGemini($apiKey, $payload)
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . rawurlencode($apiKey);
    $body = json_encode($payload);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ));
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            error_log('[TPT] Gemini cURL error: ' . $err);
            return null;
        }
        return json_decode($response, true);
    }

    $context = stream_context_create(array(
        'http' => array(
            'method'        => 'POST',
            'header'        => 'Content-Type: application/json',
            'content'       => $body,
            'timeout'       => 30,
            'ignore_errors' => true,
        ),
    ));
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }
    return json_decode($response, true);
}

function handleChatbotRequest()
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $fallback = 'Sorry, I\'m having trouble connecting. Please email us at hello@thepietechnologies.com or use the contact form.';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('success' => false, 'reply' => $fallback));
        return;
    }

    $raw     = file_get_contents('php://input');
    $input   = json_decode($raw, true);
    if (!is_array($input)) {
        $input = array();
    }

    $message   = isset($input['message']) ? mb_substr(trim((string) $input['message']), 0, 1500) : '';
    $sessionId = isset($input['session_id']) ? mb_substr(trim((string) $input['session_id']), 0, 100) : '';
    $leadName  = isset($input['name']) ? mb_substr(trim((string) $input['name']), 0, 150) : '';
    $leadEmail = isset($input['email']) ? mb_substr(trim((string) $input['email']), 0, 150) : '';
    $history   = isset($input['history']) && is_array($input['history']) ? $input['history'] : array();

    if ($message === '') {
        echo json_encode(array('success' => false, 'reply' => $fallback));
        return;
    }

    /* ---------------- store / update the chat lead ---------------- */
    if (DB_OK && $sessionId !== '' && ($leadName !== '' || $leadEmail !== '')) {
        $existing = dbOne('SELECT id FROM chatbot_leads WHERE session_id = ?', array($sessionId));
        if ($existing) {
            dbExec('UPDATE chatbot_leads SET name = COALESCE(NULLIF(?, ""), name), email = COALESCE(NULLIF(?, ""), email) WHERE id = ?',
                array($leadName, $leadEmail, (int) $existing['id']));
        } else {
            dbInsert('INSERT INTO chatbot_leads (session_id, name, email) VALUES (?, ?, ?)', array($sessionId, $leadName, $leadEmail));
        }
    }

    /* ------------------------ call Gemini ------------------------ */
    $apiKey = getSetting('gemini_api_key');
    if ($apiKey === '') {
        echo json_encode(array('success' => false, 'reply' => $fallback));
        return;
    }

    $systemInstruction = getSetting('chatbot_system_prompt', chatbotDefaultPrompt());

    /* sanitize history into Gemini's shape, keep the last 12 turns */
    $contents = array();
    foreach (array_slice($history, -12) as $turn) {
        if (!is_array($turn)) { continue; }
        $role = isset($turn['role']) && $turn['role'] === 'model' ? 'model' : 'user';
        $text = '';
        if (isset($turn['parts']) && is_array($turn['parts'])) {
            foreach ($turn['parts'] as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $text .= (string) $part['text'];
                }
            }
        }
        $text = mb_substr(trim($text), 0, 1500);
        if ($text !== '') {
            $contents[] = array('role' => $role, 'parts' => array(array('text' => $text)));
        }
    }
    $contents[] = array('role' => 'user', 'parts' => array(array('text' => $message)));

    $payload = array(
        'system_instruction' => array('parts' => array(array('text' => $systemInstruction))),
        'contents'           => $contents,
        'generationConfig'   => array('maxOutputTokens' => 300, 'temperature' => 0.7),
    );

    $decoded = chatbotCallGemini($apiKey, $payload);

    $reply = null;
    if (is_array($decoded) && isset($decoded['candidates'][0]['content']['parts'])) {
        foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $reply .= $part['text'];
            }
        }
    }
    if ($reply === null || trim($reply) === '') {
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            error_log('[TPT] Gemini API error: ' . $decoded['error']['message']);
        }
        echo json_encode(array('success' => false, 'reply' => $fallback));
        return;
    }

    echo json_encode(array('success' => true, 'reply' => trim($reply)));
}

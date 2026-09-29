<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Alia backend (the TPT growth assistant)
 * ---------------------------------------------------------------------------
 *  Accepts POST JSON: {message, history[], session_id, name, email, phone,
 *  company, service, csrf_token}. Talks to whichever AI provider the admin
 *  has selected (core/AIProviders.php — two slots, one active). API keys
 *  never leave the server. All failure modes answer with a professional
 *  fallback message so the chat UI never gets stuck.
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}
require_once BASE_PATH . '/core/AIProviders.php';

function chatbotDefaultPrompt()
{
    return "You are Alia, the growth assistant for The Pie Technologies (TPT) — never call yourself a chatbot, bot or AI bot. TPT is a growth agency across five disciplines — GROW (Meta Ads, Social Media Management, Google Ads, Digital Marketing), GET FOUND (SEO, Local SEO, AI Business Optimization), BUILD (Website Development, App Development), CREATE (Graphic Design) and MEASURE (Data Analytics & Reporting). Locations: Collingswood, NJ, USA and Punjab, Pakistan. Contact: info@thepietechnologies.com, +1 (213) 257 8242. Answer only from real TPT information: services, the six-step process (Discover, Strategize, Build, Launch, Optimize, Scale), the free Growth Library resources, published case studies and testimonials. NEVER invent pricing, statistics, results, client names or availability. If asked about pricing, explain engagements are scoped per goal and market, and offer to capture their details for a written quote. If you are not sure of an answer, say exactly: I don't want to guess. You can speak with the TPT team here — and point them to the contact page. Help visitors pick the right service or blueprint for their goal, suggest relevant free Growth Library resources, and when they show buying intent, encourage them to start a project via the contact page. Be concise, warm and specific. Stay on topic: TPT services, growth strategy and the agency. If asked something unrelated, politely redirect.";
}

/** The visitor-facing connection-error message (Task 13). */
function chatbotErrorMessage()
{
    return getSetting('alia_error', 'I’m having trouble connecting right now. Please try again in a moment.');
}

/**
 * Make sure a chat row exists for this session. Anonymous visitors still get a
 * conversation record (identified by their session id) so the Admin → Alia →
 * Chats dashboard can show every conversation, not only captured leads.
 */
function aliaEnsureChat($sessionId, $name = '', $email = '')
{
    if (!DB_OK) {
        return 0;
    }
    $existing = dbOne('SELECT id FROM chatbot_leads WHERE session_id = ?', array($sessionId));
    if ($existing) {
        return (int) $existing['id'];
    }
    $newId = dbInsert(
        'INSERT INTO chatbot_leads (session_id, name, email, ip_address) VALUES (?, ?, ?, ?)',
        array($sessionId, $name, $email, pieClientIp())
    );
    return $newId > 0 ? (int) $newId : 0;
}

/**
 * Append one turn to the transcript and keep the chat row's summary current
 * (last message + counter) so the dashboard can list conversations quickly.
 */
function aliaLogMessage($sessionId, $role, $content)
{
    if (!DB_OK) {
        return;
    }
    $content = mb_substr(trim((string) $content), 0, 8000);
    if ($content === '') {
        return;
    }
    $role = $role === 'assistant' ? 'assistant' : 'user';
    dbExec(
        'INSERT INTO chatbot_messages (session_id, role, content) VALUES (?, ?, ?)',
        array($sessionId, $role, $content)
    );
    dbExec(
        'UPDATE chatbot_leads SET last_message = ?, message_count = message_count + 1 WHERE session_id = ?',
        array(mb_substr($content, 0, 1000), $sessionId)
    );
}

/**
 * Legacy helper kept for existing callers (admin test action).
 * Routes through the configured provider stack.
 */
function chatbotCallGemini($apiKey, $payload)
{
    $model = getSetting('gemini_model', 'gemini-2.5-flash');
    if (!preg_match('/^[a-zA-Z0-9._-]{1,100}$/D', $model)) { return null; }
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    $body = json_encode($payload);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'x-goog-api-key: ' . $apiKey),
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
            'header'        => "Content-Type: application/json\r\nx-goog-api-key: " . $apiKey,
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

/**
 * Keep answers short: trim anything well beyond a readable chat bubble at a
 * sentence boundary so Alia never returns a wall of text.
 */
function aliaTrimReply($reply, $limit = 700)
{
    $reply = trim((string) $reply);
    if (mb_strlen($reply) <= $limit) {
        return $reply;
    }
    $cut = mb_substr($reply, 0, $limit);
    /* Prefer the last sentence end inside the window. */
    $lastStop = 0;
    foreach (array('. ', '! ', '? ', ".\n", "!\n", "?\n") as $needle) {
        $pos = mb_strrpos($cut, $needle);
        if ($pos !== false && $pos > $lastStop) {
            $lastStop = $pos + mb_strlen(rtrim($needle));
        }
    }
    if ($lastStop > (int) ($limit * 0.45)) {
        return rtrim(mb_substr($reply, 0, $lastStop));
    }
    /* No sentence boundary: fall back to the last word boundary. */
    $space = mb_strrpos($cut, ' ');
    return rtrim($space > 0 ? mb_substr($reply, 0, $space) : $cut) . '…';
}

function handleChatbotRequest()
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $fallback = chatbotErrorMessage();

    if (getSetting('alia_enabled', '1') !== '1') {
        echo json_encode(array('success' => false, 'reply' => $fallback, 'code' => 'disabled'));
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); header('Allow: POST');
        echo json_encode(array('success' => false, 'reply' => $fallback, 'code' => 'method'));
        return;
    }

    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = array();
    }

    if (!validateCSRF(isset($input['csrf_token']) ? $input['csrf_token'] : null)) { http_response_code(403); echo json_encode(array('success' => false, 'reply' => 'Please refresh the page and try again.', 'code' => 'csrf')); return; }
    foreach (array('message', 'name', 'email', 'phone', 'company', 'service') as $key) { if (isset($input[$key]) && !is_string($input[$key])) { http_response_code(422); echo json_encode(array('success' => false, 'reply' => $fallback, 'code' => 'invalid')); return; } }
    if (!Ratelimit::allow('chat:' . pieClientIp(), 20, 600)) { http_response_code(429); echo json_encode(array('success' => false, 'reply' => 'I’m receiving too many messages right now — please give me a moment and try again.', 'code' => 'rate_limit')); return; }

    $message   = isset($input['message']) ? mb_substr(trim((string) $input['message']), 0, 1500) : '';
    $sessionId = hash('sha256', session_id()); // Do not let clients overwrite another visitor’s lead.
    $leadName  = isset($input['name']) ? mb_substr(trim((string) $input['name']), 0, 150) : '';
    $leadEmail = isset($input['email']) ? mb_substr(trim((string) $input['email']), 0, 150) : '';
    $history   = isset($input['history']) && is_array($input['history']) ? $input['history'] : array();

    if ($message === '') {
        echo json_encode(array('success' => false, 'reply' => $fallback, 'code' => 'empty'));
        return;
    }

    /* ---------------- store / update the chat lead (category: lead) -------- */
    /* Every conversation is tracked; personal details are stored only when the
       visitor actually shares a name + valid email. */
    if (DB_OK) {
        aliaEnsureChat($sessionId);
    }
    if (DB_OK && getSetting('alia_lead_collection', '1') === '1' && $leadName !== '' && filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
        $existing = dbOne('SELECT id, name, email FROM chatbot_leads WHERE session_id = ?', array($sessionId));
        if ($existing) {
            dbExec('UPDATE chatbot_leads SET name = COALESCE(NULLIF(?, ""), name), email = COALESCE(NULLIF(?, ""), email) WHERE id = ?',
                array($leadName, $leadEmail, (int) $existing['id']));
        } else {
            $newLeadId = dbInsert('INSERT INTO chatbot_leads (session_id, name, email) VALUES (?, ?, ?)', array($sessionId, $leadName, $leadEmail));
            if ($newLeadId > 0) {
                require_once BASE_PATH . '/core/Notifications.php';
                $leadRows = EmailTemplates::detailTable(array(
                    'Name'    => esc($leadName),
                    'Email'   => esc($leadEmail),
                    'Source'  => 'Alia chat assistant',
                    'Date'    => esc(date('j M Y, H:i') . ' UTC'),
                ));
                Notifications::notifyAdmins('lead', 'lead_admin', array(
                    'name'  => $leadName,
                    'email' => $leadEmail,
                ), array('table' => $leadRows, 'admin_url' => rtrim(SITE_URL, '/') . '/admin/leads.php'));
            }
        }
        $conversation = array();
        foreach (array_slice($history, -12) as $turn) {
            if (is_array($turn) && isset($turn['content'])) {
                $conversation[] = array('role' => isset($turn['role']) ? $turn['role'] : 'user', 'content' => (string) $turn['content']);
            } elseif (is_array($turn) && isset($turn['parts'])) {
                $text = '';
                foreach ($turn['parts'] as $part) { if (is_array($part) && isset($part['text'])) { $text .= $part['text']; } }
                $conversation[] = array('role' => isset($turn['role']) && $turn['role'] === 'model' ? 'assistant' : 'user', 'content' => $text);
            }
        }
        $conversation[] = array('role' => 'user', 'content' => $message);
        dbExec('UPDATE chatbot_leads SET phone = ?, company = ?, service = ?, conversation = ? WHERE session_id = ?',
            array(mb_substr(sanitize(isset($input['phone']) ? $input['phone'] : ''), 0, 30), mb_substr(sanitize(isset($input['company']) ? $input['company'] : ''), 0, 150), mb_substr(sanitize(isset($input['service']) ? $input['service'] : ''), 0, 100), mb_substr(json_encode($conversation, JSON_UNESCAPED_UNICODE), 0, 15000), $sessionId));
    }

    /* Record the visitor's question as soon as it is accepted. */
    aliaLogMessage($sessionId, 'user', $message);

    /* ------------------------- call the active provider ------------------- */
    $system = getSetting('chatbot_system_prompt', '');
    if (trim($system) === '') {
        $system = chatbotDefaultPrompt();
    }
    /* Keep answers short and direct, whatever the admin prompt says. */
    $system .= "\n\nRESPONSE STYLE (always applies): Answer the question directly in 2–4 short sentences (about 60 words maximum) unless the visitor explicitly asks for detail or a step-by-step list. Lead with the answer — no preamble, no restating the question, no filler. Offer the next step in one short sentence when it helps.";

    /* Normalise history to simple role/content turns (both legacy + new shape). */
    $turns = array();
    foreach (array_slice($history, -12) as $turn) {
        if (!is_array($turn)) { continue; }
        $role = isset($turn['role']) && ($turn['role'] === 'model' || $turn['role'] === 'assistant') ? 'assistant' : 'user';
        if (isset($turn['content'])) {
            $text = mb_substr(trim((string) $turn['content']), 0, 1500);
        } elseif (isset($turn['parts']) && is_array($turn['parts'])) {
            $text = '';
            foreach ($turn['parts'] as $part) { if (is_array($part) && isset($part['text'])) { $text .= (string) $part['text']; } }
            $text = mb_substr(trim($text), 0, 1500);
        } else {
            $text = '';
        }
        if ($text !== '') {
            $turns[] = array('role' => $role, 'content' => $text);
        }
    }
    $turns[] = array('role' => 'user', 'content' => $message);

    $result = AIProviders::chat($turns, $system);

    if (!$result['ok'] || trim($result['reply']) === '') {
        /* Meaningful-but-rare: alert admins at most once a day about AI outages. */
        require_once BASE_PATH . '/core/Notifications.php';
        Notifications::eventThrottled('chatbot_ai_failure', 86400, 'chatbot', 'system', array(
            'name'    => 'Alia',
            'message' => 'The chatbot AI provider is failing (' . $result['error'] . '). Visitors are seeing the fallback message.',
            'subject' => 'Chatbot AI provider failure',
        ), array('table' => ''));
        echo json_encode(array('success' => false, 'reply' => $fallback, 'code' => $result['error'] !== '' ? $result['error'] : 'provider'));
        return;
    }

    $reply = aliaTrimReply(trim($result['reply']));
    /* Record Alia's answer so the dashboard can replay the whole conversation. */
    aliaLogMessage($sessionId, 'assistant', $reply);

    echo json_encode(array('success' => true, 'reply' => $reply));
}

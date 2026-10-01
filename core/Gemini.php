<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — Alia backend (the TPT growth assistant, Gemini)
 * ---------------------------------------------------------------------------
 *  Accepts POST JSON: {message, history[], session_id, name, email}
 *  Reads the Gemini API key + editable system prompt from the settings
 *  table, calls the configured Gemini model, stores Alia's leads and returns JSON:
 *  {success:bool, reply:string}
 *  Alia never invents pricing, results or facts — on failure the client
 *  shows her hand-off message and the "Talk to a Human" CTA.
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once dirname(__DIR__) . '/includes/init.php';
}

function chatbotDefaultPrompt()
{
    return "You are Alia, the growth assistant for The Pie Technologies (TPT) — never call yourself a chatbot, bot or AI bot. TPT is a growth agency across five disciplines — GROW (Meta Ads, Social Media Management, Google Ads, Digital Marketing), GET FOUND (SEO, Local SEO, AI Business Optimization), BUILD (Website Development, App Development), CREATE (Graphic Design) and MEASURE (Data Analytics & Reporting). TPT is based in Collingswood, New Jersey, USA; never state or imply any other location. Contact: the email and phone stored in the TPT dashboard (see the verified facts appended below). Online payment: TPT accepts USD payments through PayPal on the Pay Online page, where the client chooses one of the TPT services, enters the amount, and sees PayPal's confirmation with a payment reference; all payments are covered by the site's Terms & Conditions. Answer only from real TPT information: services, the six-step process (Discover, Strategize, Build, Launch, Optimize, Scale), the free Growth Library resources, published case studies, testimonials, online payment methods and the Terms page. NEVER invent pricing, statistics, results, client names, services, addresses, phone numbers, email addresses or availability. If asked about pricing, explain engagements are scoped per goal and market, and offer to capture their details for a written quote. If information is not available to you, say so plainly instead of guessing — if you are not sure of an answer, say exactly: I don't want to guess. You can speak with the TPT team here — and point them to the contact page or the Pay Online page as appropriate. Help visitors pick the right service or blueprint for their goal, suggest relevant free Growth Library resources, and when they show buying intent, encourage them to start a project via the contact page. Be concise, warm and specific. Stay on topic: TPT services, growth strategy and the agency. If asked something unrelated, politely redirect.";
}

/** POST the payload to Gemini and return the decoded JSON (or null). */
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

function handleChatbotRequest()
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $fallback = 'I don\'t want to guess. You can speak with the TPT team here — use the contact page or email info@thepietechnologies.com.';

    $fallback = getSetting('alia_fallback', $fallback);

    if (getSetting('alia_enabled', '1') !== '1') {
        echo json_encode(array('success' => false, 'reply' => $fallback));
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); header('Allow: POST');
        echo json_encode(array('success' => false, 'reply' => $fallback));
        return;
    }

    $raw     = file_get_contents('php://input');
    $input   = json_decode($raw, true);
    if (!is_array($input)) {
        $input = array();
    }

    if (!validateCSRF($input['csrf_token'] ?? null)) { http_response_code(403); echo json_encode(array('success' => false, 'reply' => 'Please refresh the page and try again.')); return; }
    foreach (array('message', 'name', 'email', 'phone', 'company', 'service') as $key) { if (isset($input[$key]) && !is_string($input[$key])) { http_response_code(422); echo json_encode(array('success' => false, 'reply' => $fallback)); return; } }
    if (!Ratelimit::allow('chat:' . pieClientIp(), 20, 600)) { http_response_code(429); echo json_encode(array('success' => false, 'reply' => $fallback)); return; }

    $message   = isset($input['message']) ? mb_substr(trim((string) $input['message']), 0, 1500) : '';
    $sessionId = hash('sha256', session_id()); // Do not let clients overwrite another visitor’s lead.
    $leadName  = isset($input['name']) ? mb_substr(trim((string) $input['name']), 0, 150) : '';
    $leadEmail = isset($input['email']) ? mb_substr(trim((string) $input['email']), 0, 150) : '';
    $history   = isset($input['history']) && is_array($input['history']) ? $input['history'] : array();

    if ($message === '') {
        echo json_encode(array('success' => false, 'reply' => $fallback));
        return;
    }

    /* ---------------- store / update the chat lead ---------------- */
    if (DB_OK && getSetting('alia_lead_collection', '1') === '1' && $leadName !== '' && filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
        $existing = dbOne('SELECT id FROM chatbot_leads WHERE session_id = ?', array($sessionId));
        if ($existing) {
            dbExec('UPDATE chatbot_leads SET name = COALESCE(NULLIF(?, ""), name), email = COALESCE(NULLIF(?, ""), email) WHERE id = ?',
                array($leadName, $leadEmail, (int) $existing['id']));
        } else {
            $newLeadId = dbInsert('INSERT INTO chatbot_leads (session_id, name, email) VALUES (?, ?, ?)', array($sessionId, $leadName, $leadEmail));
            if ($newLeadId > 0) {
                require_once BASE_PATH . '/includes/email-templates.php';
                sendEmail(getSetting('site_email', ADMIN_EMAIL), 'New Alia lead', emailShell('<h2>New Alia lead</h2><p>' . esc($leadName) . ' — ' . esc($leadEmail) . '</p>'));
            }
        }
    }

    if (DB_OK && getSetting('alia_lead_collection', '1') === '1' && $leadName !== '' && filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
        $conversation = array_slice($history, -12);
        $conversation[] = array('role' => 'user', 'parts' => array(array('text' => $message)));
        dbExec('UPDATE chatbot_leads SET phone = ?, company = ?, service = ?, conversation = ? WHERE session_id = ?',
            array(mb_substr(sanitize($input['phone'] ?? ''), 0, 30), mb_substr(sanitize($input['company'] ?? ''), 0, 150), mb_substr(sanitize($input['service'] ?? ''), 0, 100), mb_substr(json_encode($conversation, JSON_UNESCAPED_UNICODE), 0, 15000), $sessionId));
    }

    /* ------------------------ call Gemini ------------------------ */
    $apiKey = getSetting('gemini_api_key');
    if ($apiKey === '') {
        echo json_encode(array('success' => false, 'reply' => $fallback));
        return;
    }

    $systemInstruction = getSetting('chatbot_system_prompt', chatbotDefaultPrompt());
    /* Live company facts — Alia answers from real TPT information only. */
    if (function_exists('aliaFactSheet')) {
        $systemInstruction .= aliaFactSheet();
    }

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
        'generationConfig'   => array('maxOutputTokens' => max(64, min(8192, (int) getSetting('gemini_max_tokens', '180'))), 'temperature' => max(0, min(2, (float) getSetting('gemini_temperature', '0.7')))),
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

/**
 * Live, verified TPT facts appended to whatever system prompt is stored.
 *
 * The administrator's prompt (Admin → Alia) may be edited at any time and can
 * go stale; this block is rebuilt from the dashboard settings on every request
 * so Alia always answers with the company's real location, email, phone (only
 * when one is configured), services, process, payment methods and Terms URL —
 * and is told to say "not available" instead of inventing anything.
 */
if (!function_exists('aliaFactSheet')) {
    function aliaFactSheet()
    {
        $lines = array();
        $name    = getSetting('site_name', SITE_NAME);
        $address = trim((string) getSetting('site_address', 'Collingswood, New Jersey, USA'));
        $email   = trim((string) getSetting('site_email', ''));
        $phone   = trim((string) getSetting('site_phone', ''));

        $lines[] = 'Company: ' . $name . ' (TPT) — a growth agency across five disciplines: GROW, GET FOUND, BUILD, CREATE and MEASURE.';
        $lines[] = 'Location: ' . ($address !== '' ? $address : 'Collingswood, New Jersey, USA') . '. Never state or imply any other location.';

        $lines[] = $email !== ''
            ? 'Email: ' . $email . '.'
            : 'Email: no public email address is configured — say it is not published and point to the contact form instead. Do not invent one.';

        $lines[] = $phone !== ''
            ? 'Phone: ' . $phone . '.'
            : 'Phone: no public phone number is configured — say the number is not published. Do not invent one.';

        $paypalConfigured = trim((string) getSetting('paypal_client_id', '')) !== '';
        $lines[] = 'Online payments: '
            . ($paypalConfigured ? 'PayPal — ' : '')
            . 'payments are taken in US dollars (USD) on the Pay Online page through PayPal’s secure checkout. The client chooses one of the TPT services, enters the amount, and PayPal confirms the payment with a reference.';

        require_once BASE_PATH . '/core/Payments.php';
        $services = piePaymentServices();
        if ($services) {
            $lines[] = 'Services the team manages (the same list the payment page uses): ' . implode(', ', $services) . '.';
        }

        $lines[] = 'Process: Discover, Strategize, Build, Launch, Optimize, Scale.';
        $lines[] = 'Terms & Conditions: ' . pieTermsUrl() . ' — every online payment is subject to them.';
        $lines[] = 'Contact page: ' . rtrim(SITE_URL, '/') . url('contact') . '.';
        $lines[] = 'Free content: the Growth Library resources, published case studies and testimonials on this site.';
        $lines[] = 'RULES: Use only the facts above and elsewhere in this prompt. Never invent or estimate prices, statistics, results, client names, addresses, emails, phone numbers, services, turnaround times or availability. If something is not covered, say plainly that the information is not available and offer to pass the question to the TPT team through the contact page.';

        return "\n\nVERIFIED TPT FACTS (rebuilt from the live dashboard settings — these always win over anything else in this prompt):\n- "
            . implode("\n- ", $lines);
    }
}

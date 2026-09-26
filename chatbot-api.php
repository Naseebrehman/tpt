<?php
/**
 * ---------------------------------------------------------------------------
 *  PIE Bot public endpoint — proxies chat to the Google Gemini API.
 *  The implementation lives in /includes/chatbot-api.php (protected from
 *  direct access by .htaccess); this file is the routed public entry.
 * ---------------------------------------------------------------------------
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/chatbot-api.php';

handleChatbotRequest();

<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();
require_once BASE_PATH . '/core/Settings.php';

$adminPage  = 'settings';
$adminTitle = 'Settings';

$textKeys = array(
    'smtp_reply_to', 'gemini_model', 'gemini_temperature', 'gemini_max_tokens', 'alia_welcome', 'alia_fallback',
    'brand_primary', 'brand_secondary', 'brand_accent', 'brand_font', 'stripe_webhook_secret',
    'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'smtp_from_name', 'smtp_from_email',
    'gemini_api_key', 'chatbot_system_prompt',
    'site_name', 'site_tagline', 'site_phone', 'site_email', 'site_address', 'whatsapp_number',
    'google_analytics_id', 'facebook_pixel_id', 'meta_title', 'meta_description', 'founder_name', 'maintenance_ip',
    'instagram_url', 'facebook_url', 'linkedin_url', 'tiktok_url', 'twitter_url', 'youtube_url',
    'stripe_mode', 'stripe_publishable_key', 'stripe_secret_key',
    'paypal_mode', 'paypal_client_id', 'paypal_secret',
);

$toggleKeys = array('sample_content_enabled', 'alia_lead_collection', 'maintenance_mode', 'alia_enabled', 'stripe_enabled', 'paypal_enabled', 'pay_online_enabled');

function saveSetting($key, $value)
{
    if (dbExec(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        array($key, $value)
    ) < 0) { $GLOBALS['settingsSaveFailed'] = true; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired.');
    } elseif (($validationError = Settings::validate($_POST)) !== '') {
        setFlash('err', $validationError);
    } else {
        foreach ($textKeys as $key) {
            if (isset($_POST[$key])) {
                $secretKeys = array('smtp_pass', 'gemini_api_key', 'stripe_secret_key', 'stripe_webhook_secret', 'paypal_secret');
                if (in_array($key, $secretKeys, true) && $_POST[$key] === '') { continue; }
                saveSetting($key, in_array($key, $secretKeys, true) ? trim($_POST[$key]) : sanitizeMultiline($_POST[$key]));
            }
        }
        foreach ($toggleKeys as $toggleKey) {
            saveSetting($toggleKey, isset($_POST[$toggleKey]) ? '1' : '0');
        }

        foreach (array('brand_logo', 'brand_favicon') as $imageKey) {
            $image = uploadFile($imageKey, 'settings', array('jpg', 'jpeg', 'png', 'webp'));
            if (!$image['ok']) { setFlash('err', $image['error']); }
            elseif ($image['path'] !== '') { saveSetting($imageKey, $image['path']); }
        }
        $ogUp = uploadFile('og_image', 'settings', array('jpg', 'jpeg', 'png', 'webp'));
        if ($ogUp['ok'] && $ogUp['path'] !== '') {
            $old = getSetting('og_image');
            if ($old !== '' && $old !== 'assets/images/og-image.jpg') { deleteUpload($old); }
            saveSetting('og_image', $ogUp['path']);
        } elseif (!$ogUp['ok']) {
            setFlash('err', $ogUp['error']);
        }
        if (!empty($GLOBALS['settingsSaveFailed'])) { setFlash('err', 'Settings could not be saved. Check the database connection.'); }
        if (empty($_SESSION['flash'])) {
            setFlash('ok', 'Settings saved.');
        }
        header('Location: settings.php');
        exit;
    }
}

$adminUser = currentAdmin();

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<form method="post" enctype="multipart/form-data">
    <?= csrfField() ?>
    <p class="hint">Saved passwords and secret keys are never rendered. Leave blank to keep an existing secret; replace to rotate. Save before testing SMTP or Gemini.</p>

    <div class="a-tabs">
        <button class="a-tab active" type="button" data-tab="smtp">SMTP</button>
        <button class="a-tab" type="button" data-tab="gemini">Alia (Gemini)</button>
        <button class="a-tab" type="button" data-tab="payments">Payments</button>
        <button class="a-tab" type="button" data-tab="branding">Branding</button>
        <button class="a-tab" type="button" data-tab="site">Site Settings</button>
        <button class="a-tab" type="button" data-tab="social">Social Media</button>
        <button class="a-tab" type="button" data-tab="maintenance">Maintenance</button>
    </div>

    <div class="a-tabpanel active" data-panel="smtp">
        <div class="a-card">
            <h3>SMTP / email delivery</h3>
            <div class="a-field-row">
                <div class="a-field"><label for="smtp_host">Host</label><input id="smtp_host" name="smtp_host" type="text" value="<?= esc(getSetting('smtp_host')) ?>" placeholder="smtp.hostinger.com"></div>
                <div class="a-field"><label for="smtp_port">Port</label><input id="smtp_port" name="smtp_port" type="number" value="<?= esc(getSetting('smtp_port', '587')) ?>"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field">
                    <label for="smtp_encryption">Encryption</label>
                    <select id="smtp_encryption" name="smtp_encryption">
                        <?php foreach (array('tls' => 'TLS (STARTTLS, port 587)', 'ssl' => 'SSL (SMTPS, port 465)', 'none' => 'None') as $encKey => $encLabel): ?>
                        <option value="<?= $encKey ?>"<?= getSetting('smtp_encryption', 'tls') === $encKey ? ' selected' : '' ?>><?= $encLabel ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="a-field"><label for="smtp_user">Username</label><input id="smtp_user" name="smtp_user" type="text" value="<?= esc(getSetting('smtp_user')) ?>" autocomplete="off"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field">
                    <label for="smtp_pass">Password</label>
                    <span class="pw-wrap"><input id="smtp_pass" name="smtp_pass" type="password" value="" autocomplete="new-password"><button class="pw-toggle" type="button" data-target="smtp_pass">Show</button></span>
                </div>
                <div class="a-field"><label for="smtp_from_name">From name</label><input id="smtp_from_name" name="smtp_from_name" type="text" value="<?= esc(getSetting('smtp_from_name', getSetting('site_name', SITE_NAME))) ?>"></div>
            </div>
            <div class="a-field">
                <label for="smtp_from_email">From email</label>
                <input id="smtp_from_email" name="smtp_from_email" type="email" value="<?= esc(getSetting('smtp_from_email', getSetting('site_email', ADMIN_EMAIL))) ?>">
            </div>
            <div class="a-field"><label for="smtp_reply_to">Reply-To</label><input type="email" name="smtp_reply_to" id="smtp_reply_to" value="<?= esc(getSetting('smtp_reply_to')) ?>"></div>
            <div class="a-toolbar">
                <input type="email" id="test_email_to" value="<?= esc($adminUser ? $adminUser['email'] : ADMIN_EMAIL) ?>" placeholder="Send test to…" style="background:#0c0c12;border:1px solid var(--line);border-radius:10px;padding:9px 12px;color:var(--text)">
                <button class="a-btn" type="button" data-ajax-action="test_email" data-fields="test_email_to" data-result="smtpTest">Send Test Email</button>
            </div>
            <div class="inline-test" id="smtpTest"></div>
        </div>
    </div>

    <div class="a-tabpanel" data-panel="gemini">
        <div class="a-card">
            <h3>Alia — the TPT growth assistant</h3>
            <?php foreach (array('gemini_model' => array('Model', 'gemini-2.5-flash'), 'gemini_temperature' => array('Temperature (0–2)', '0.7'), 'gemini_max_tokens' => array('Maximum tokens (64–8192)', '300'), 'alia_welcome' => array('Welcome message', "Hi, I'm Alia. How can I help you today?"), 'alia_fallback' => array('Fallback message', 'Please contact our team for help.')) as $key => $field): ?>
            <div class="a-field"><label for="<?= esc($key) ?>"><?= esc($field[0]) ?></label><input id="<?= esc($key) ?>" name="<?= esc($key) ?>" value="<?= esc(getSetting($key, $field[1])) ?>"></div>
            <?php endforeach; ?>
            <label class="a-check"><input type="checkbox" name="alia_lead_collection" value="1"<?= getSetting('alia_lead_collection', '1') === '1' ? ' checked' : '' ?>> Offer optional name/email lead collection</label>

            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="alia_enabled" value="1"<?= getSetting('alia_enabled', '1') === '1' ? ' checked' : '' ?>> Alia is ON — widget visible site-wide and the API answers requests</label>
                <div class="hint">Turning this off removes the widget from every page and disables the chat endpoint.</div>
            </div>
            <div class="a-field">
                <label for="gemini_api_key">Gemini API key</label>
                <span class="pw-wrap"><input id="gemini_api_key" name="gemini_api_key" type="password" value="" autocomplete="new-password" placeholder="AIza…"><button class="pw-toggle" type="button" data-target="gemini_api_key">Show</button></span>
                <div class="hint">Create a free key at Google AI Studio → &ldquo;Get API key&rdquo;. Stored only in your database — never exposed to the frontend.</div>
            </div>
            <div class="a-field">
                <label for="chatbot_system_prompt">Alia system prompt</label>
                <textarea id="chatbot_system_prompt" name="chatbot_system_prompt" style="min-height:160px"><?= esc(getSetting('chatbot_system_prompt')) ?></textarea>
                <div class="hint">Edit how Alia behaves. She must never invent pricing or results — keep the hand-off rule (&ldquo;I don&rsquo;t want to guess…&rdquo;) in the prompt. Leave empty to restore the default on next save.</div>
            </div>
            <div class="a-toolbar">
                <button class="a-btn" type="button" data-ajax-action="test_gemini" data-result="geminiTest">Test Connection</button>
            </div>
            <div class="inline-test" id="geminiTest"></div>
        </div>
    </div>

    <div class="a-tabpanel" data-panel="payments">
        <div class="a-card">
            <h3>Pay Online page</h3>
            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="pay_online_enabled" value="1"<?= getSetting('pay_online_enabled', '1') === '1' ? ' checked' : '' ?>> Pay Online page is ON</label>
                <div class="hint">When off, /pay-online shows a &ldquo;payments currently unavailable&rdquo; notice and providers are hidden. Only providers enabled below ever appear on the page.</div>
            </div>
        </div>
        <div class="a-card">
            <h3>Stripe</h3>
            <div class="a-field"><label for="stripe_webhook_secret">Webhook signing secret</label><input type="password" id="stripe_webhook_secret" name="stripe_webhook_secret" value="" autocomplete="new-password"><div class="hint">Endpoint: /api/webhooks/stripe — checkout.session.completed and checkout.session.async_payment_succeeded. Blank keeps saved secret.</div></div>
            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="stripe_enabled" value="1"<?= getSetting('stripe_enabled', '0') === '1' ? ' checked' : '' ?>> Stripe enabled — shown as a payment option</label>
            </div>
            <div class="a-field">
                <label for="stripe_mode">Mode</label>
                <select id="stripe_mode" name="stripe_mode">
                    <option value="test"<?= getSetting('stripe_mode', 'test') === 'test' ? ' selected' : '' ?>>Test mode (no real charges)</option>
                    <option value="live"<?= getSetting('stripe_mode', 'test') === 'live' ? ' selected' : '' ?>>Live mode (real charges)</option>
                </select>
                <div class="hint">Use test keys in test mode and live keys in live mode — mixing them will fail.</div>
            </div>
            <div class="a-field">
                <label for="stripe_publishable_key">Publishable key</label>
                <input id="stripe_publishable_key" name="stripe_publishable_key" type="text" value="<?= esc(getSetting('stripe_publishable_key')) ?>" placeholder="pk_test_…" autocomplete="off">
            </div>
            <div class="a-field">
                <label for="stripe_secret_key">Secret key</label>
                <span class="pw-wrap"><input id="stripe_secret_key" name="stripe_secret_key" type="password" value="" autocomplete="new-password" placeholder="sk_test…"><button class="pw-toggle" type="button" data-target="stripe_secret_key">Show</button></span>
                <div class="hint">Server-side only. Never printed into page source or JavaScript.</div>
            </div>
        </div>
        <div class="a-card">
            <h3>PayPal</h3>
            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="paypal_enabled" value="1"<?= getSetting('paypal_enabled', '0') === '1' ? ' checked' : '' ?>> PayPal enabled — shown as a payment option</label>
            </div>
            <div class="a-field">
                <label for="paypal_mode">Mode</label>
                <select id="paypal_mode" name="paypal_mode">
                    <option value="sandbox"<?= getSetting('paypal_mode', 'sandbox') === 'sandbox' ? ' selected' : '' ?>>Sandbox (no real charges)</option>
                    <option value="live"<?= getSetting('paypal_mode', 'sandbox') === 'live' ? ' selected' : '' ?>>Live (real charges)</option>
                </select>
            </div>
            <div class="a-field">
                <label for="paypal_client_id">Client ID</label>
                <input id="paypal_client_id" name="paypal_client_id" type="text" value="<?= esc(getSetting('paypal_client_id')) ?>" autocomplete="off">
            </div>
            <div class="a-field">
                <label for="paypal_secret">Secret</label>
                <span class="pw-wrap"><input id="paypal_secret" name="paypal_secret" type="password" value="" autocomplete="new-password"><button class="pw-toggle" type="button" data-target="paypal_secret">Show</button></span>
                <div class="hint">Server-side only, like the Stripe secret.</div>
            </div>
        </div>
    </div>

    <div class="a-tabpanel" data-panel="branding"><div class="a-card"><h3>Branding Settings</h3>
        <p>Defaults preserve the current design. Site name, contact/footer copy, social links and OG image remain in their existing tabs.</p>
        <?php foreach (array('brand_logo' => 'Logo', 'brand_favicon' => 'Favicon') as $key => $label): ?>
        <div class="a-field"><label for="<?= $key ?>"><?= $label ?></label><input type="file" id="<?= $key ?>" name="<?= $key ?>" accept="image/png,image/jpeg,image/webp"><small><?= esc(getSetting($key, 'Existing brand asset')) ?></small></div>
        <?php endforeach; ?>
        <?php foreach (array('brand_primary' => '#7c3aed', 'brand_secondary' => '#22d3ee', 'brand_accent' => '#f59e0b') as $key => $default): ?>
        <div class="a-field"><label for="<?= $key ?>"><?= esc(ucwords(str_replace('_', ' ', $key))) ?></label><input type="color" id="<?= $key ?>" name="<?= $key ?>" value="<?= esc(getSetting($key, $default)) ?>"></div>
        <?php endforeach; ?>
        <div class="a-field"><label for="brand_font">Typography</label><select id="brand_font" name="brand_font"><option value="default">Existing brand fonts</option><option value="system"<?= getSetting('brand_font') === 'system' ? ' selected' : '' ?>>System fonts</option></select></div>
    </div></div>
    <div class="a-tabpanel" data-panel="site">
        <div class="a-card">
            <h3>Sample content</h3>
            <label class="a-check"><input type="checkbox" name="sample_content_enabled" value="1"<?= getSetting('sample_content_enabled','1') === '1' ? ' checked' : '' ?>> Show clearly labeled samples when a content table is empty or the database is offline</label>
            <p class="hint">Existing records are never replaced. To edit the samples in your dashboard, use the button below. It adds three articles, three resources and three fictional portfolio projects, skipping sample slugs already present.</p>
            <button class="a-btn" type="button" data-ajax-action="seed_samples" data-result="samplesResult">Add editable sample content</button><div class="inline-test" id="samplesResult"></div>
            <h3>Agency information</h3>
            <div class="a-field-row">
                <div class="a-field"><label for="site_name">Agency name</label><input id="site_name" name="site_name" type="text" value="<?= esc(getSetting('site_name', SITE_NAME)) ?>"></div>
                <div class="a-field"><label for="founder_name">Founder name (home page)</label><input id="founder_name" name="founder_name" type="text" value="<?= esc(getSetting('founder_name')) ?>"></div>
            </div>
            <div class="a-field"><label for="site_tagline">Tagline / footer description</label><textarea id="site_tagline" name="site_tagline" style="min-height:70px"><?= esc(getSetting('site_tagline')) ?></textarea></div>
            <div class="a-field-row">
                <div class="a-field"><label for="site_phone">Phone</label><input id="site_phone" name="site_phone" type="text" value="<?= esc(getSetting('site_phone')) ?>"></div>
                <div class="a-field"><label for="whatsapp_number">WhatsApp number (floating button)</label><input id="whatsapp_number" name="whatsapp_number" type="text" value="<?= esc(getSetting('whatsapp_number')) ?>" placeholder="+92 300 1234567"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field"><label for="site_email">Public email</label><input id="site_email" name="site_email" type="email" value="<?= esc(getSetting('site_email')) ?>"></div>
                <div class="a-field"><label for="site_address">Address</label><input id="site_address" name="site_address" type="text" value="<?= esc(getSetting('site_address')) ?>"></div>
            </div>
            <h3 style="margin-top:26px">SEO &amp; analytics</h3>
            <div class="a-field-row">
                <div class="a-field"><label for="google_analytics_id">Google Analytics ID</label><input id="google_analytics_id" name="google_analytics_id" type="text" value="<?= esc(getSetting('google_analytics_id')) ?>" placeholder="G-XXXXXXXXXX"></div>
                <div class="a-field"><label for="facebook_pixel_id">Meta Pixel ID</label><input id="facebook_pixel_id" name="facebook_pixel_id" type="text" value="<?= esc(getSetting('facebook_pixel_id')) ?>"></div>
            </div>
            <div class="a-field"><label for="meta_title">Default meta title</label><input id="meta_title" name="meta_title" type="text" value="<?= esc(getSetting('meta_title')) ?>"></div>
            <div class="a-field"><label for="meta_description">Default meta description</label><textarea id="meta_description" name="meta_description" style="min-height:70px"><?= esc(getSetting('meta_description')) ?></textarea></div>
            <div class="a-field">
                <label for="og_image">Default OG image</label>
                <input id="og_image" name="og_image" type="file" accept="image/jpeg,image/png,image/webp">
                <div class="hint">Current: <?= esc(getSetting('og_image', 'assets/images/og-image.jpg')) ?> (1200×630 recommended)</div>
            </div>
        </div>
    </div>

    <div class="a-tabpanel" data-panel="social">
        <div class="a-card">
            <h3>Social profiles</h3>
            <div class="a-field-row">
                <div class="a-field"><label for="instagram_url">Instagram</label><input id="instagram_url" name="instagram_url" type="url" value="<?= esc(getSetting('instagram_url')) ?>"></div>
                <div class="a-field"><label for="facebook_url">Facebook</label><input id="facebook_url" name="facebook_url" type="url" value="<?= esc(getSetting('facebook_url')) ?>"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field"><label for="linkedin_url">LinkedIn</label><input id="linkedin_url" name="linkedin_url" type="url" value="<?= esc(getSetting('linkedin_url')) ?>"></div>
                <div class="a-field"><label for="tiktok_url">TikTok</label><input id="tiktok_url" name="tiktok_url" type="url" value="<?= esc(getSetting('tiktok_url')) ?>"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field"><label for="twitter_url">Twitter / X</label><input id="twitter_url" name="twitter_url" type="url" value="<?= esc(getSetting('twitter_url')) ?>"></div>
                <div class="a-field"><label for="youtube_url">YouTube</label><input id="youtube_url" name="youtube_url" type="url" value="<?= esc(getSetting('youtube_url')) ?>"></div>
            </div>
        </div>
    </div>

    <div class="a-tabpanel" data-panel="maintenance">
        <div class="a-card">
            <h3>Maintenance mode</h3>
            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="maintenance_mode" value="1"<?= getSetting('maintenance_mode', '0') === '1' ? ' checked' : '' ?>> Maintenance mode ON — visitors see the maintenance page</label>
                <div class="hint">Logged-in admins always pass through, as does the IP below.</div>
            </div>
            <div class="a-field">
                <label for="maintenance_ip">Allowed IP (bypass)</label>
                <input id="maintenance_ip" name="maintenance_ip" type="text" value="<?= esc(getSetting('maintenance_ip')) ?>" placeholder="e.g. 82.114.0.1 — leave empty to allow none">
            </div>
        </div>
    </div>

    <div class="a-toolbar" style="margin-top:6px">
        <button class="a-btn primary" type="submit"><?= icon('check', 16) ?> Save All Settings</button>
        <a class="a-btn" href="password.php">Change Password</a>
    </div>
</form>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>

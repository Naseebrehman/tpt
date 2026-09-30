<?php
require_once dirname(__DIR__) . '/includes/init.php';
requireAdmin();
require_once BASE_PATH . '/core/Settings.php';
require_once BASE_PATH . '/core/EmailTemplates.php';
require_once BASE_PATH . '/core/Notifications.php';
require_once BASE_PATH . '/core/Captcha.php';
require_once BASE_PATH . '/core/AIProviders.php';

$adminPage  = 'settings';
$adminTitle = 'Settings';

$textKeys = array(
    'smtp_reply_to', 'gemini_model', 'gemini_temperature', 'gemini_max_tokens', 'alia_welcome', 'alia_fallback',
    'alia_error', 'chatbot_name', 'chatbot_max_tokens', 'chatbot_temperature',
    'ai_provider1_type', 'ai_provider1_model', 'ai_provider1_base_url', 'ai_provider1_api_key',
    'ai_provider2_type', 'ai_provider2_model', 'ai_provider2_base_url', 'ai_provider2_api_key', 'ai_active_provider',
    'captcha_provider', 'captcha_site_key', 'captcha_secret_key', 'phone_default_country',
    'brand_primary', 'brand_secondary', 'brand_accent', 'brand_font',
    'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'smtp_from_name', 'smtp_from_email',
    'gemini_api_key', 'chatbot_system_prompt',
    'site_name', 'site_tagline', 'site_phone', 'site_email', 'site_address', 'whatsapp_number',
    'google_analytics_id', 'facebook_pixel_id', 'meta_title', 'meta_description', 'founder_name', 'maintenance_ip',
    'instagram_url', 'facebook_url', 'linkedin_url', 'tiktok_url', 'twitter_url', 'youtube_url',
);

$toggleKeys = array(
    'sample_content_enabled', 'alia_lead_collection', 'maintenance_mode', 'alia_enabled',
    'ai_provider1_enabled', 'ai_provider2_enabled', 'captcha_enabled',
);
/* Notification category toggles live on the Notifications tab (notif_cats_save). */

$secretKeys = array('smtp_pass', 'gemini_api_key',
    'ai_provider1_api_key', 'ai_provider2_api_key', 'captcha_secret_key');

function saveSetting($key, $value)
{
    if (dbExec(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        array($key, $value)
    ) < 0) { $GLOBALS['settingsSaveFailed'] = true; }
}

function settingsRedirect($anchor = '')
{
    header('Location: settings.php' . ($anchor !== '' ? '#' . $anchor : ''));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        setFlash('err', 'Security token expired.');
    } elseif (isset($_POST['tpl_save'])) {
        /* ---------- Email template save (Task 10) ---------- */
        $key     = preg_replace('/[^a-z0-9_]/', '', (string) (isset($_POST['template_key']) ? $_POST['template_key'] : ''));
        $subject = sanitizeMultiline(isset($_POST['tpl_subject']) ? $_POST['tpl_subject'] : '');
        $body    = trim((string) (isset($_POST['tpl_body']) ? $_POST['tpl_body'] : ''));
        $active  = isset($_POST['tpl_active']);
        if (!isset(EmailTemplates::defaults()[$key])) {
            setFlash('err', 'Unknown template.');
        } elseif (mb_strlen($body) > 100000) {
            setFlash('err', 'Template body is too long.');
        } elseif (EmailTemplates::save($key, $subject, $body, $active)) {
            setFlash('ok', 'Template saved.');
        } else {
            setFlash('err', 'Template could not be saved.');
        }
        settingsRedirect('templates');
    } elseif (isset($_POST['notif_add'])) {
        /* ---------- Notification recipients (Task 8) ---------- */
        $email = sanitize(isset($_POST['notif_email']) ? $_POST['notif_email'] : '');
        $cats  = isset($_POST['notif_categories']) && is_array($_POST['notif_categories']) ? $_POST['notif_categories'] : array();
        list($ok, $message) = Notifications::addRecipient($email, $cats, true);
        setFlash($ok ? 'ok' : 'err', $message);
        settingsRedirect('notifications');
    } elseif (isset($_POST['notif_delete'])) {
        setFlash(Notifications::removeRecipient((int) (isset($_POST['notif_id']) ? $_POST['notif_id'] : 0)) ? 'ok' : 'err', 'Recipient removed.');
        settingsRedirect('notifications');
    } elseif (isset($_POST['notif_edit'])) {
        /* Edit an existing notification email (address + categories). */
        $id    = (int) (isset($_POST['notif_id']) ? $_POST['notif_id'] : 0);
        $email = sanitize(isset($_POST['notif_email']) ? $_POST['notif_email'] : '');
        $cats  = isset($_POST['cats']) && is_array($_POST['cats']) ? $_POST['cats'] : array();
        list($ok, $message) = Notifications::updateRecipient($id, $email, $cats);
        setFlash($ok ? 'ok' : 'err', $message);
        settingsRedirect('notifications');
    } elseif (isset($_POST['notif_toggle'])) {
        $id = (int) (isset($_POST['notif_id']) ? $_POST['notif_id'] : 0);
        $active = isset($_POST['active']) && $_POST['active'] === '1';
        Notifications::setRecipientActive($id, $active);
        setFlash('ok', $active ? 'Recipient enabled.' : 'Recipient disabled.');
        settingsRedirect('notifications');
    } elseif (isset($_POST['notif_categories_save'])) {
        $id = (int) (isset($_POST['notif_id']) ? $_POST['notif_id'] : 0);
        $cats = isset($_POST['cats']) && is_array($_POST['cats']) ? $_POST['cats'] : array();
        if (Notifications::setRecipientCategories($id, $cats)) {
            setFlash('ok', 'Categories updated.');
        } else {
            setFlash('err', 'Pick at least one category.');
        }
        settingsRedirect('notifications');
    } elseif (isset($_POST['notif_cats_save'])) {
        foreach (array_keys(Notifications::categories()) as $cat) {
            saveSetting('notify_cat_' . $cat, isset($_POST['notify_cat_' . $cat]) ? '1' : '0');
        }
        setFlash(empty($GLOBALS['settingsSaveFailed']) ? 'ok' : 'err', empty($GLOBALS['settingsSaveFailed']) ? 'Notification categories saved.' : 'Could not save.');
        settingsRedirect('notifications');
    } else {
        /* ---------- Main settings save ---------- */
        $validationError = Settings::validate($_POST);
        if ($validationError !== '') {
            setFlash('err', $validationError);
        } else {
            foreach ($textKeys as $key) {
                if (isset($_POST[$key])) {
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
        }
        settingsRedirect();
    }
}

$adminUser = currentAdmin();

/* Which template is being edited (Task 10). */
$templateLabels = EmailTemplates::keys();
$currentTemplate = isset($_GET['template']) ? preg_replace('/[^a-z0-9_]/', '', (string) $_GET['template']) : 'contact_admin';
if (!isset($templateLabels[$currentTemplate])) { $currentTemplate = 'contact_admin'; }
$templateData = EmailTemplates::get($currentTemplate);

/* Sample values for the template preview. */
$previewVars = array(
    'name' => 'John Smith', 'email' => 'john@example.com', 'phone' => '+1 (555) 123-4567',
    'service' => 'Digital Marketing', 'amount' => '1,500.00', 'payment_method' => 'PayPal',
    'transaction_id' => 'TXN-9F2K4L8Q', 'message' => 'This is a sample message used for the template preview.',
    'subject' => 'Sample subject', 'company' => 'Acme Inc.', 'reference' => 'TPT-2026-0148',
    'status' => 'Paid', 'ip' => '203.0.113.10', 'budget' => '$1000–$2500',
);
$previewCompose = EmailTemplates::compose($currentTemplate, $previewVars, array(
    'table' => EmailTemplates::detailTable(array(
        'Name' => 'John Smith', 'Email' => 'john@example.com', 'Sample' => 'Preview row',
    )),
    'admin_url' => rtrim(SITE_URL, '/') . '/admin/',
));

require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="a-tabs">
    <button class="a-tab active" type="button" data-tab="general">General</button>
    <button class="a-tab" type="button" data-tab="payments">Payments</button>
    <button class="a-tab" type="button" data-tab="smtp">Email / SMTP</button>
    <button class="a-tab" type="button" data-tab="notifications">Notifications</button>
    <button class="a-tab" type="button" data-tab="templates">Email Templates</button>
    <button class="a-tab" type="button" data-tab="ai">AI / Alia</button>
    <button class="a-tab" type="button" data-tab="forms">Forms</button>
    <button class="a-tab" type="button" data-tab="maintenance">Maintenance</button>
</div>

<form method="post" enctype="multipart/form-data" id="settingsForm">
    <?= csrfField() ?>
    <p class="hint">Saved passwords and secret keys are never rendered. Leave blank to keep an existing secret; replace to rotate. Save before testing SMTP or the AI provider.</p>

    <!-- ============================ GENERAL ============================ -->
    <div class="a-tabpanel active" data-panel="general">
        <div class="a-card">
            <h3>Agency information</h3>
            <div class="a-field-row">
                <div class="a-field"><label for="site_name">Agency name</label><input id="site_name" name="site_name" type="text" value="<?= esc(getSetting('site_name', SITE_NAME)) ?>"></div>
                <div class="a-field"><label for="founder_name">Founder name (home page)</label><input id="founder_name" name="founder_name" type="text" value="<?= esc(getSetting('founder_name')) ?>"></div>
            </div>
            <div class="a-field"><label for="site_tagline">Tagline / footer description</label><textarea id="site_tagline" name="site_tagline" style="min-height:70px"><?= esc(getSetting('site_tagline')) ?></textarea></div>
            <div class="a-field-row">
                <div class="a-field"><label for="site_phone">Phone</label><input id="site_phone" name="site_phone" type="text" value="<?= esc(getSetting('site_phone')) ?>"></div>
                <div class="a-field"><label for="whatsapp_number">WhatsApp number (floating button)</label><input id="whatsapp_number" name="whatsapp_number" type="text" value="<?= esc(getSetting('whatsapp_number')) ?>" placeholder="+1 555 1234567"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field"><label for="site_email">Public email</label><input id="site_email" name="site_email" type="email" value="<?= esc(getSetting('site_email')) ?>"></div>
                <div class="a-field"><label for="site_address">Address</label><input id="site_address" name="site_address" type="text" value="<?= esc(getSetting('site_address')) ?>"></div>
            </div>
        </div>
        <div class="a-card">
            <h3>Branding</h3>
            <p class="hint" style="margin-top:-8px">Defaults preserve the current design. Branding is applied to the website and to every outgoing email.</p>
            <?php foreach (array('brand_logo' => 'Logo', 'brand_favicon' => 'Favicon') as $key => $label): ?>
            <div class="a-field"><label for="<?= $key ?>"><?= $label ?></label><input type="file" id="<?= $key ?>" name="<?= $key ?>" accept="image/png,image/jpeg,image/webp"><small><?= esc(getSetting($key, 'Existing brand asset')) ?></small></div>
            <?php endforeach; ?>
            <?php foreach (array('brand_primary' => '#7c3aed', 'brand_secondary' => '#22d3ee', 'brand_accent' => '#f59e0b') as $key => $default): ?>
            <div class="a-field"><label for="<?= $key ?>"><?= esc(ucwords(str_replace('_', ' ', $key))) ?></label><input type="color" id="<?= $key ?>" name="<?= $key ?>" value="<?= esc(getSetting($key, $default)) ?>"></div>
            <?php endforeach; ?>
            <div class="a-field"><label for="brand_font">Typography</label><select id="brand_font" name="brand_font"><option value="default">Existing brand fonts</option><option value="system"<?= getSetting('brand_font') === 'system' ? ' selected' : '' ?>>System fonts</option></select></div>
        </div>
        <div class="a-card">
            <h3>SEO &amp; analytics</h3>
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
        <div class="a-card">
            <h3>Sample content</h3>
            <label class="a-check"><input type="checkbox" name="sample_content_enabled" value="1"<?= getSetting('sample_content_enabled','1') === '1' ? ' checked' : '' ?>> Show clearly labeled samples when a content table is empty or the database is offline</label>
            <p class="hint">Existing records are never replaced. To edit the samples in your dashboard, use the button below. It adds three articles, three resources and three fictional portfolio projects, skipping sample slugs already present.</p>
            <button class="a-btn" type="button" data-ajax-action="seed_samples" data-result="samplesResult">Add editable sample content</button><div class="inline-test" id="samplesResult"></div>
        </div>
    </div>

    <!-- ============================ PAYMENTS ============================ -->
    <div class="a-tabpanel" data-panel="payments">
        <div class="a-card">
            <h3>Payment page</h3>
            <p class="hint">The Pay Online page renders the custom PayPal and Stripe payment code you save in Admin &rarr; Payments — your complete HTML, CSS and JavaScript, stored and displayed exactly as provided. This site stores no gateway credentials and makes no provider API calls.</p>
            <div class="a-toolbar">
                <a class="a-btn" href="<?= esc(url('admin/payments')) ?>"><?= icon('card', 15) ?> Manage payment code</a>
                <a class="a-btn" href="<?= esc(url('pay-online')) ?>" target="_blank" rel="noopener">Preview Pay Online</a>
            </div>
        </div>
    </div>

    <!-- ============================ EMAIL / SMTP ============================ -->
    <div class="a-tabpanel" data-panel="smtp">
        <div class="a-card">
            <h3>SMTP / email delivery</h3>
            <div class="a-field-row">
                <div class="a-field"><label for="smtp_host">SMTP Host</label><input id="smtp_host" name="smtp_host" type="text" value="<?= esc(getSetting('smtp_host')) ?>" placeholder="smtp.hostinger.com"></div>
                <div class="a-field"><label for="smtp_port">SMTP Port</label><input id="smtp_port" name="smtp_port" type="number" value="<?= esc(getSetting('smtp_port', '587')) ?>"></div>
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
                <div class="a-field"><label for="smtp_user">SMTP Username</label><input id="smtp_user" name="smtp_user" type="text" value="<?= esc(getSetting('smtp_user')) ?>" autocomplete="off"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field">
                    <label for="smtp_pass">SMTP Password</label>
                    <span class="pw-wrap"><input id="smtp_pass" name="smtp_pass" type="password" value="" autocomplete="new-password"><button class="pw-toggle" type="button" data-target="smtp_pass">Show</button></span>
                    <div class="hint"><?= getSetting('smtp_pass') !== '' ? 'A password is saved. Leave blank to keep it.' : 'Not saved yet.' ?> Never shown back or exposed via the API.</div>
                </div>
                <div class="a-field"><label for="smtp_from_name">From Name</label><input id="smtp_from_name" name="smtp_from_name" type="text" value="<?= esc(getSetting('smtp_from_name', getSetting('site_name', SITE_NAME))) ?>"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field">
                    <label for="smtp_from_email">From Email</label>
                    <input id="smtp_from_email" name="smtp_from_email" type="email" value="<?= esc(getSetting('smtp_from_email', getSetting('site_email', ADMIN_EMAIL))) ?>">
                </div>
                <div class="a-field"><label for="smtp_reply_to">Reply-To Email</label><input type="email" name="smtp_reply_to" id="smtp_reply_to" value="<?= esc(getSetting('smtp_reply_to')) ?>"></div>
            </div>
            <div class="a-toolbar">
                <input type="email" id="test_email_to" value="<?= esc($adminUser ? $adminUser['email'] : ADMIN_EMAIL) ?>" placeholder="Send test to…" style="background:#0c0c12;border:1px solid var(--line);border-radius:10px;padding:9px 12px;color:var(--text)">
                <button class="a-btn" type="button" data-ajax-action="test_email" data-fields="test_email_to" data-result="smtpTest">Send Test Email</button>
            </div>
            <div class="inline-test" id="smtpTest"></div>
        </div>
    </div>

    <!-- ============================ AI / ALIA ============================ -->
    <div class="a-tabpanel" data-panel="ai">
        <div class="a-card">
            <h3>AI Chatbot — general</h3>
            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="alia_enabled" value="1"<?= getSetting('alia_enabled', '1') === '1' ? ' checked' : '' ?>> Chatbot enabled — widget visible site-wide and the API answers requests</label>
                <div class="hint">Turning this off removes the widget from every page and disables the chat endpoint.</div>
            </div>
            <div class="a-field-row">
                <div class="a-field"><label for="chatbot_name">Chatbot name</label><input id="chatbot_name" name="chatbot_name" type="text" value="<?= esc(getSetting('chatbot_name', 'Alia')) ?>" placeholder="Alia"></div>
                <div class="a-field"><label for="chatbot_max_tokens">Maximum response length (tokens)</label><input id="chatbot_max_tokens" name="chatbot_max_tokens" type="number" value="<?= esc(getSetting('chatbot_max_tokens', getSetting('gemini_max_tokens', '300'))) ?>"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field"><label for="chatbot_temperature">Temperature (0–2)</label><input id="chatbot_temperature" name="chatbot_temperature" type="number" step="0.1" value="<?= esc(getSetting('chatbot_temperature', getSetting('gemini_temperature', '0.7'))) ?>"></div>
                <div class="a-field"><label for="alia_welcome">Welcome message</label><input id="alia_welcome" name="alia_welcome" type="text" value="<?= esc(getSetting('alia_welcome', "Hi, I'm Alia. How can I help you today?")) ?>"></div>
            </div>
            <div class="a-field-row">
                <div class="a-field"><label for="alia_error">Error message (AI unavailable)</label><input id="alia_error" name="alia_error" type="text" value="<?= esc(getSetting('alia_error', 'I’m having trouble connecting right now. Please try again in a moment.')) ?>"></div>
                <div class="a-field"><label for="alia_fallback">Fallback message (no confident answer)</label><input id="alia_fallback" name="alia_fallback" type="text" value="<?= esc(getSetting('alia_fallback', "I don't want to guess. You can speak with the TPT team here.")) ?>"></div>
            </div>
            <div class="a-field">
                <label for="chatbot_system_prompt">System prompt</label>
                <textarea id="chatbot_system_prompt" name="chatbot_system_prompt" style="min-height:160px"><?= esc(getSetting('chatbot_system_prompt')) ?></textarea>
                <div class="hint">Edit how the assistant behaves. It must never invent pricing or results — keep the hand-off rule in the prompt. Leave empty to restore the default on next request.</div>
            </div>
            <label class="a-check"><input type="checkbox" name="alia_lead_collection" value="1"<?= getSetting('alia_lead_collection', '1') === '1' ? ' checked' : '' ?>> Offer optional name/email lead collection</label>
        </div>

        <div class="a-card">
            <h3>Active provider</h3>
            <div class="a-field">
                <label for="ai_active_provider">The website always uses this provider</label>
                <select id="ai_active_provider" name="ai_active_provider">
                    <option value="1"<?= AIProviders::activeSlot() === 1 ? ' selected' : '' ?>>Provider 1</option>
                    <option value="2"<?= AIProviders::activeSlot() === 2 ? ' selected' : '' ?>>Provider 2</option>
                </select>
                <div class="hint">Only one provider is active at a time. Visitors never choose — the website uses whichever is selected here.</div>
            </div>
        </div>

        <?php foreach (array(1, 2) as $slot): $cfg = AIProviders::config($slot); ?>
        <div class="a-card">
            <h3>Provider <?= $slot ?><?= $slot === 1 ? ' — default: Google Gemini (free tier available)' : ' — default: OpenAI-compatible' ?></h3>
            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="ai_provider<?= $slot ?>_enabled" value="1"<?= $cfg['enabled'] ? ' checked' : '' ?>> Provider <?= $slot ?> enabled</label>
            </div>
            <div class="a-field-row">
                <div class="a-field">
                    <label for="ai_provider<?= $slot ?>_type">Provider type</label>
                    <select id="ai_provider<?= $slot ?>_type" name="ai_provider<?= $slot ?>_type">
                        <?php foreach (AIProviders::types() as $typeKey => $typeLabel): ?>
                        <option value="<?= esc($typeKey) ?>"<?= $cfg['type'] === $typeKey ? ' selected' : '' ?>><?= esc($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="a-field">
                    <label for="ai_provider<?= $slot ?>_model">Model</label>
                    <input id="ai_provider<?= $slot ?>_model" name="ai_provider<?= $slot ?>_model" type="text" value="<?= esc($cfg['model']) ?>" placeholder="<?= $slot === 1 ? 'gemini-2.5-flash' : 'gpt-4o-mini' ?>">
                    <div class="hint"><?= $slot === 1 ? 'Google AI Studio offers a free tier for Gemini models.' : 'Works with OpenAI, OpenRouter, Groq, DeepSeek and others — several have free tiers.' ?></div>
                </div>
            </div>
            <div class="a-field">
                <label for="ai_provider<?= $slot ?>_api_key">API key (server-side only)</label>
                <span class="pw-wrap"><input id="ai_provider<?= $slot ?>_api_key" name="ai_provider<?= $slot ?>_api_key" type="password" value="" autocomplete="new-password" placeholder="<?= $cfg['api_key'] !== '' ? '•••••••• (saved)' : 'Not saved yet' ?>"><button class="pw-toggle" type="button" data-target="ai_provider<?= $slot ?>_api_key">Show</button></span>
                <div class="hint">Stored only in the database and used server-side — never exposed to the browser.</div>
            </div>
            <div class="a-field">
                <label for="ai_provider<?= $slot ?>_base_url">API base URL (OpenAI-compatible only)</label>
                <input id="ai_provider<?= $slot ?>_base_url" name="ai_provider<?= $slot ?>_base_url" type="text" value="<?= esc($cfg['base_url']) ?>" placeholder="https://api.openai.com/v1">
                <div class="hint">Examples: https://api.openai.com/v1 · https://openrouter.ai/api/v1 · https://api.groq.com/openai/v1</div>
            </div>
            <div class="a-toolbar">
                <button class="a-btn" type="button" data-ajax-action="test_ai" data-fields="ai_provider<?= $slot ?>_slot" data-result="aiTest<?= $slot ?>">Test Provider <?= $slot ?></button>
                <input type="hidden" id="ai_provider<?= $slot ?>_slot" value="<?= $slot ?>">
            </div>
            <div class="inline-test" id="aiTest<?= $slot ?>"></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============================ FORMS ============================ -->
    <div class="a-tabpanel" data-panel="forms">
        <div class="a-card">
            <h3>CAPTCHA / anti-spam</h3>
            <div class="a-field">
                <label class="a-check"><input type="checkbox" name="captcha_enabled" value="1"<?= getSetting('captcha_enabled', '0') === '1' ? ' checked' : '' ?>> CAPTCHA enabled on public forms (contact, payment, lead, newsletter)</label>
            </div>
            <div class="a-field">
                <label for="captcha_provider">Provider</label>
                <select id="captcha_provider" name="captcha_provider">
                    <?php foreach (Captcha::providers() as $capKey => $capLabel): ?>
                    <option value="<?= esc($capKey) ?>"<?= Captcha::provider() === $capKey ? ' selected' : '' ?>><?= esc($capLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="a-field-row">
                <div class="a-field"><label for="captcha_site_key">Site key (public)</label><input id="captcha_site_key" name="captcha_site_key" type="text" value="<?= esc(getSetting('captcha_site_key')) ?>" autocomplete="off"></div>
                <div class="a-field">
                    <label for="captcha_secret_key">Secret key (server-side only)</label>
                    <span class="pw-wrap"><input id="captcha_secret_key" name="captcha_secret_key" type="password" value="" autocomplete="new-password" placeholder="<?= getSetting('captcha_secret_key') !== '' ? '•••••••• (saved)' : 'Not saved yet' ?>"><button class="pw-toggle" type="button" data-target="captcha_secret_key">Show</button></span>
                </div>
            </div>
            <p class="hint">The secret key is verified server-side on every submission and is never exposed in frontend JavaScript or public API responses.</p>
        </div>
        <div class="a-card">
            <h3>Contact &amp; phone settings</h3>
            <div class="a-field">
                <label for="phone_default_country">Default phone country</label>
                <select id="phone_default_country" name="phone_default_country">
                    <?php foreach (array('us' => 'United States (+1)', 'gb' => 'United Kingdom (+44)', 'pk' => 'Pakistan (+92)', 'in' => 'India (+91)', 'ca' => 'Canada (+1)', 'au' => 'Australia (+61)') as $cKey => $cLabel): ?>
                    <option value="<?= $cKey ?>"<?= getSetting('phone_default_country', 'us') === $cKey ? ' selected' : '' ?>><?= $cLabel ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">The phone input defaults to this country but every visitor can pick another — numbers are stored in international format.</div>
            </div>
            <p class="hint">The contact form collects only: name, email, phone, service, message and an optional company name. All public forms are CSRF-protected, honeypot-guarded, rate-limited and validated server-side.</p>
        </div>
    </div>

    <!-- ============================ MAINTENANCE ============================ -->
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

    <div class="a-toolbar settings-save-bar" style="margin-top:6px">
        <button class="a-btn primary" type="submit" name="save_settings" value="1"><?= icon('check', 16) ?> Save All Settings</button>
        <a class="a-btn" href="password.php">Change Password</a>
    </div>
</form>

<!-- ============================ NOTIFICATIONS ============================ -->
<div class="a-tabpanel" data-panel="notifications">
    <form method="post">
        <?= csrfField() ?>
        <div class="a-card">
            <h3>Notification categories</h3>
            <p class="hint" style="margin-top:-8px">Turn each category ON/OFF. Only enabled categories email the recipients below.</p>
            <?php foreach (Notifications::categories() as $catKey => $catLabel): ?>
            <label class="a-check" style="margin-bottom:10px">
                <input type="checkbox" name="notify_cat_<?= $catKey ?>" value="1"<?= Notifications::categoryEnabled($catKey) ? ' checked' : '' ?>>
                <span><strong><?= esc($catLabel) ?></strong>
                    <span class="hint" style="display:block;margin-top:2px">
                        <?= $catKey === 'contact' ? 'Contact form submissions' : ($catKey === 'payment' ? 'Completed payments and invoice requests' : ($catKey === 'lead' ? 'Leads captured by the chat assistant' : ($catKey === 'chatbot' ? 'Only meaningful chatbot events (e.g. AI outage alerts — rate-limited)' : ($catKey === 'system' ? 'Important system/admin notifications' : 'Security events (login lockouts, password changes)')))) ?>
                    </span>
                </span>
            </label>
            <?php endforeach; ?>
            <div class="a-toolbar" style="margin-top:8px">
                <button class="a-btn primary" type="submit" name="notif_cats_save" value="1"><?= icon('check', 16) ?> Save Categories</button>
            </div>
        </div>
    </form>

    <div class="a-card">
        <h3>Notification recipients</h3>
        <p class="hint" style="margin-top:-8px">Add as many addresses as you need (e.g. admin@, accounts@, marketing@, support@). Recipients are never hard-coded — with no list configured, the site email receives everything.</p>
        <form method="post" class="a-toolbar" style="margin-bottom:16px">
            <?= csrfField() ?>
            <input type="email" name="notif_email" required placeholder="accounts@domain.com" style="background:#0c0c12;border:1px solid var(--line);border-radius:10px;padding:9px 12px;color:var(--text);min-width:220px">
            <button class="a-btn primary" type="submit" name="notif_add" value="1">Add Email</button>
        </form>

        <?php $recipients = Notifications::recipients(); ?>
        <?php if ($recipients): ?>
        <div class="a-table-wrap">
            <table class="a-table">
                <thead><tr><th>Email</th><th>Status</th><th>Categories</th><th style="text-align:right">Actions</th></tr></thead>
                <tbody>
                    <?php foreach ($recipients as $recipient): ?>
                    <tr>
                        <td>
                            <form method="post" class="a-toolbar" style="gap:8px;flex-wrap:nowrap;margin:0">
                                <?= csrfField() ?>
                                <input type="hidden" name="notif_id" value="<?= (int) $recipient['id'] ?>">
                                <input type="email" name="notif_email" value="<?= esc($recipient['email']) ?>" required aria-label="Email address" style="min-width:170px">
                                <button class="a-btn" type="submit" name="notif_edit" value="1" style="padding:5px 10px;font-size:.72rem;white-space:nowrap"><?= icon('check', 13) ?> Save</button>
                            </form>
                        </td>
                        <td><span class="chip"><?= $recipient['is_active'] ? 'Enabled' : 'Disabled' ?></span></td>
                        <td>
                            <form method="post" class="a-toolbar" style="gap:8px;margin:0">
                                <?= csrfField() ?>
                                <input type="hidden" name="notif_id" value="<?= (int) $recipient['id'] ?>">
                                <?php foreach (Notifications::categories() as $catKey => $catLabel): ?>
                                <label class="a-check" style="font-size:.72rem;gap:4px">
                                    <input type="checkbox" name="cats[]" value="<?= $catKey ?>"<?= in_array($catKey, array_map('trim', explode(',', $recipient['categories'])), true) ? ' checked' : '' ?>> <?= esc($catLabel) ?>
                                </label>
                                <?php endforeach; ?>
                                <button class="a-btn" type="submit" name="notif_categories_save" value="1" style="padding:5px 10px;font-size:.72rem">Save</button>
                            </form>
                        </td>
                        <td>
                            <div class="row-actions">
                                <form method="post"><?= csrfField() ?><input type="hidden" name="notif_id" value="<?= (int) $recipient['id'] ?>"><input type="hidden" name="active" value="<?= $recipient['is_active'] ? '0' : '1' ?>"><button class="a-btn" type="submit" name="notif_toggle" value="1" style="padding:5px 10px;font-size:.72rem"><?= $recipient['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                                <form method="post" data-confirm="Remove this notification recipient?"><?= csrfField() ?><input type="hidden" name="notif_id" value="<?= (int) $recipient['id'] ?>"><button class="a-btn danger" type="submit" name="notif_delete" value="1" style="padding:5px 10px;font-size:.72rem">Remove</button></form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint" style="margin-top:12px">Edit an address and press Save — the categories are saved with it. Notifications are delivered to every enabled recipient for that category.</p>
        <?php else: ?>
        <p class="hint">No recipients configured yet — notifications go to <strong><?= esc(getSetting('site_email', ADMIN_EMAIL)) ?></strong>.</p>
        <?php endif; ?>

        <div class="a-card" style="margin-top:18px">
            <h3>Verify delivery</h3>
            <p class="hint" style="margin-top:-8px">Sends one real notification through the list above so you can confirm the saved addresses receive mail (requires working SMTP settings).</p>
            <div class="a-toolbar">
                <select id="notif_test_category" aria-label="Category to test">
                    <?php foreach (Notifications::categories() as $catKey => $catLabel): ?>
                    <option value="<?= esc($catKey) ?>"><?= esc($catLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="a-btn" type="button" data-ajax-action="test_notification" data-fields="notif_test_category" data-result="notifTestResult">Send test notification</button>
            </div>
            <div class="inline-test" id="notifTestResult"></div>
        </div>
    </div>
</div>

<!-- ============================ EMAIL TEMPLATES ============================ -->
<div class="a-tabpanel" data-panel="templates">
    <div class="a-card">
        <h3>Email templates</h3>
        <p class="hint" style="margin-top:-8px">Every outgoing email uses the shared brand layout with your logo, colours and footer. Edit subjects and content below — the <code>{{variables}}</code> are replaced automatically before sending.</p>
        <div class="a-toolbar" style="margin-bottom:16px">
            <?php foreach ($templateLabels as $tplKey => $tplLabel): ?>
            <a class="a-btn<?= $tplKey === $currentTemplate ? ' primary' : '' ?>" href="settings.php?template=<?= esc($tplKey) ?>#templates"><?= esc($tplLabel) ?></a>
            <?php endforeach; ?>
        </div>

        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="template_key" value="<?= esc($currentTemplate) ?>">
            <div class="a-field-row">
                <div class="a-field">
                    <label for="tpl_subject">Subject</label>
                    <input id="tpl_subject" name="tpl_subject" type="text" value="<?= esc($templateData['subject']) ?>">
                </div>
                <div class="a-field">
                    <label>Status</label>
                    <label class="a-check"><input type="checkbox" name="tpl_active" value="1"<?= $templateData['is_active'] ? ' checked' : '' ?>> Template enabled (disable to stop this email)</label>
                    <div class="hint">Source: <?= $templateData['source'] === 'database' ? 'customized (saved in the database)' : 'built-in default' ?>. Clear both fields and save to restore the default.</div>
                </div>
            </div>
            <div class="a-field">
                <label for="tpl_body">Email body (HTML allowed)</label>
                <textarea id="tpl_body" name="tpl_body" style="min-height:220px;font-family:monospace;font-size:.82rem"><?= esc($templateData['body']) ?></textarea>
            </div>
            <div class="a-field">
                <label>Available variables</label>
                <p class="hint" style="margin:0">
                    <?php foreach (EmailTemplates::$variables as $varName): ?><code style="margin-right:8px">{{<?= esc($varName) ?>}}</code><?php endforeach; ?>
                </p>
            </div>
            <div class="a-toolbar">
                <button class="a-btn primary" type="submit" name="tpl_save" value="1"><?= icon('check', 16) ?> Save Template</button>
            </div>
        </form>
    </div>

    <div class="a-card">
        <h3>Preview — <?= esc($templateLabels[$currentTemplate]) ?></h3>
        <p class="hint" style="margin-top:-8px">Rendered with sample values inside the live brand layout.</p>
        <div style="border:1px solid var(--line);border-radius:12px;overflow:hidden">
            <iframe title="Template preview" style="width:100%;height:520px;border:0;background:#08080a" sandbox="allow-same-origin" srcdoc="<?= esc($previewCompose['html']) ?>"></iframe>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>

<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — shared site footer (+ chatbot + WhatsApp float)
 * ---------------------------------------------------------------------------
 */

if (!defined('DB_OK')) {
    require_once __DIR__ . '/init.php';
}

$services    = pieServices();
$siteName    = getSetting('site_name', SITE_NAME);
$whatsNumber = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', ''));
$socials = pieSocialLinks();
$pageLibs = isset($pageLibs) && is_array($pageLibs) ? $pageLibs : array();
?>
</main>

<?php if (!empty($contactModalEnabled)) { require __DIR__ . '/quick-contact.php'; } ?>

<footer class="site-footer footer-compact">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand" href="<?= url('') ?>"><?= esc($siteName) ?></a>
                <p><?= esc(getSetting('site_tagline', 'Clicks are easy. Growth is engineered.')) ?></p>
                <?php if ($socials): ?>
                <nav class="footer-socials" aria-label="Follow us on social media">
                    <?php foreach ($socials as $social): ?>
                    <a href="<?= esc($social['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= esc($social['label']) ?>" title="<?= esc($social['label']) ?>"><?= icon($social['icon'], 18) ?></a>
                    <?php endforeach; ?>
                </nav>
                <?php endif; ?>
            </div>
            <nav class="footer-col footer-nav" aria-label="Footer navigation">
                <h4>Explore</h4>
                <ul class="footer-links">
                    <?php foreach (array('services'=>'Services','portfolio'=>'Work','about'=>'About','blog'=>'Journal','resources'=>'Resources','pay-online'=>'Pay Online','contact'=>'Contact') as $path=>$label): ?>
                    <li><a href="<?= url($path) ?>"><?= esc($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="footer-col footer-contact">
                <h4>Let’s talk</h4>
                <ul>
                    <li><?= icon('mail', 16) ?><a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a></li>
                    <li><?= icon('phone', 16) ?><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', getSetting('site_phone', '+1 (213) 257 8242'))) ?>"><?= esc(getSetting('site_phone', '+1 (213) 257 8242')) ?></a></li>
                    <li><?= icon('pin', 16) ?><span><?= esc(getSetting('site_address', 'Collingswood, New Jersey, USA')) ?></span></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= esc($siteName) ?>. All rights reserved.</p>
            <p class="footer-legal"><a href="<?= url('privacy-policy') ?>">Privacy Policy</a><span aria-hidden="true">/</span><a href="<?= url('terms') ?>">Terms</a></p>
        </div>
    </div>
</footer>

<?php if ($whatsNumber !== ''): ?>
<a class="whatsapp-float" href="https://wa.me/<?= esc($whatsNumber) ?>?text=<?= rawurlencode('Hi ' . $siteName . '! I\'d like to talk about growing my brand.') ?>" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <?= icon('whatsapp', 26) ?>
</a>
<?php endif; ?>

<button type="button" class="back-to-top" id="backToTop" aria-label="Back to top" hidden><span aria-hidden="true">↑</span></button>

<!-- ============================ Alia chat assistant ======================== -->
<?php if (getSetting('alia_enabled', '1') === '1'): ?>
<div class="chatbot" id="chatbot">
    <?php $chatbotName = getSetting('chatbot_name', 'Alia'); ?>
    <button class="chatbot-fab" id="chatbotFab" aria-label="Ask <?= esc($chatbotName) ?>" aria-expanded="false">
        <img class="alia-portrait" src="<?= asset('assets/images/alia-portrait.jpg') ?>" alt="" width="56" height="56">
        <span class="chatbot-pulse" aria-hidden="true"></span>
    </button>

    <div class="chatbot-window" id="chatbotWindow" role="dialog" aria-label="<?= esc($chatbotName) ?> chat" aria-hidden="true">
        <div class="chatbot-head">
            <div class="chatbot-id">
                <span class="chatbot-avatar"><img class="alia-portrait" src="<?= asset('assets/images/alia-portrait.jpg') ?>" alt="" width="38" height="38"></span>
                <span class="chatbot-title">
                    <strong><?= esc($chatbotName) ?></strong>
                    <small><i class="online-dot"></i> TPT growth assistant — ask me anything</small>
                </span>
            </div>
            <button class="chatbot-close" id="chatbotClose" aria-label="Close chat"><?= icon('close', 18) ?></button>
        </div>
        <div class="chatbot-messages" id="chatbotMessages" aria-live="polite"></div>
        <div class="chatbot-input">
            <input type="text" id="chatbotInput" placeholder="Ask <?= esc($chatbotName) ?> about services, pricing, process…" autocomplete="off" aria-label="Message">
            <button id="chatbotSend" aria-label="Send message"><?= icon('send', 18) ?></button>
        </div>
        <div class="chatbot-disclaim">
            <span>AI assistant · may be imprecise</span>
            <a href="<?= url('contact') ?>">Talk to a human →</a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
window.PIE = <?= json_encode(array(
    'base' => BASE_URL,
    'siteUrl' => rtrim(SITE_URL, '/') . BASE_URL,
    'api' => asset('api/chat'),
    'csrf' => generateCSRF(),
    'fallback' => getSetting('alia_fallback', "I don't want to guess. You can speak with the TPT team here."),
    'error' => getSetting('alia_error', 'I’m having trouble connecting right now. Please try again in a moment.'),
    'name' => getSetting('chatbot_name', 'Alia'),
    'leadCollection' => getSetting('alia_lead_collection', '1') === '1',
    'welcome' => getSetting('alia_welcome', "Hi, I'm Alia. How can I help you today?"),
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js" defer></script>
<?php if (!empty($pageLibs['typed'])): ?><script src="https://cdn.jsdelivr.net/npm/typed.js@2.1.0/dist/typed.umd.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['particles'])): ?><script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['swiper'])): ?><script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['chart'])): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['sortable'])): ?><script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['phone']) || !empty($contactModalEnabled)): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.min.css">
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js" defer></script>
<?php endif; ?>
<script src="<?= asset('assets/js/main.js') ?>?v=<?= (int) filemtime(BASE_PATH . '/assets/js/main.js') ?>" defer></script>
<?php if (getSetting('alia_enabled', '1') === '1'): ?>
<script src="<?= asset('assets/js/chatbot.js') ?>?v=<?= (int) filemtime(BASE_PATH . '/assets/js/chatbot.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>

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
$socials     = array(
    array('key' => 'instagram_url', 'icon' => 'instagram', 'label' => 'Instagram'),
    array('key' => 'facebook_url',  'icon' => 'facebook',  'label' => 'Facebook'),
    array('key' => 'linkedin_url',  'icon' => 'linkedin',  'label' => 'LinkedIn'),
    array('key' => 'tiktok_url',    'icon' => 'tiktok',    'label' => 'TikTok'),
    array('key' => 'twitter_url',   'icon' => 'twitter',   'label' => 'Twitter / X'),
    array('key' => 'youtube_url',   'icon' => 'youtube',   'label' => 'YouTube'),
);
$pageLibs = isset($pageLibs) && is_array($pageLibs) ? $pageLibs : array();
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand brand-lg" href="<?= url('') ?>">The&nbsp;Pie<span class="brand-dot">.</span>&nbsp;Technologies</a>
                <p><?= esc(getSetting('site_tagline', 'Clicks are easy. Growth is engineered. A full-service growth agency across five disciplines — GROW, GET FOUND, BUILD, CREATE and MEASURE — run as one system with one owner.')) ?></p>
                <div class="footer-socials">
                    <?php foreach ($socials as $social): $href = getSetting($social['key']); if ($href !== ''): ?>
                    <a href="<?= esc($href) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= esc($social['label']) ?>"><?= icon($social['icon'], 17) ?></a>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <div class="footer-col">
                <h4>Services</h4>
                <ul>
                    <?php foreach ($services as $svc): ?>
                    <li><a href="<?= url('services/' . $svc['key']) ?>"><?= esc($svc['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="<?= url('') ?>">Home</a></li>
                    <li><a href="<?= url('portfolio') ?>">Work</a></li>
                    <li><a href="<?= url('about') ?>">About</a></li>
                    <li><a href="<?= url('blog') ?>">Journal</a></li>
                    <li><a href="<?= url('pay-online') ?>">Pay Online</a></li>
                    <li><a href="<?= url('contact') ?>">Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Growth Library</h4>
                <ul>
                    <li><a href="<?= url('resources') ?>">All resources</a></li>
                    <li><a href="<?= url('resources/lead-generation-blueprint') ?>">Lead Generation Blueprint</a></li>
                    <li><a href="<?= url('resources/seo-blueprint') ?>">The SEO Blueprint</a></li>
                    <li><a href="<?= url('resources/local-seo-blueprint') ?>">Local SEO Blueprint</a></li>
                    <li><a href="<?= url('privacy-policy') ?>">Privacy Policy</a></li>
                    <li><a href="<?= url('terms') ?>">Terms of Service</a></li>
                </ul>
            </div>

            <div class="footer-col footer-contact">
                <h4>Contact</h4>
                <ul>
                    <li><?= icon('pin', 16) ?><span><?= esc(getSetting('site_address', 'Collingswood, NJ, USA · Punjab, Pakistan')) ?></span></li>
                    <li><?= icon('phone', 16) ?><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', getSetting('site_phone', '+1 (213) 257 8242'))) ?>"><?= esc(getSetting('site_phone', '+1 (213) 257 8242')) ?></a></li>
                    <li><?= icon('mail', 16) ?><a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a></li>
                    <li><?= icon('clock', 16) ?><span>Mon – Sat, 9:00 – 19:00</span></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <?= esc($siteName) ?>. All rights reserved.</p>
            <p class="footer-credit">Made <span class="credit-heart" aria-hidden="true"><?= icon('heart', 13) ?></span> By The Pie Technologies</p>
            <p class="footer-legal">
                <a href="<?= url('privacy-policy') ?>">Privacy Policy</a>
                <span>/</span>
                <a href="<?= url('terms') ?>">Terms</a>
            </p>
        </div>
    </div>
</footer>

<?php if ($whatsNumber !== ''): ?>
<a class="whatsapp-float" href="https://wa.me/<?= esc($whatsNumber) ?>?text=<?= rawurlencode('Hi ' . $siteName . '! I\'d like to talk about growing my brand.') ?>" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <?= icon('whatsapp', 26) ?>
</a>
<?php endif; ?>

<!-- ============================ Alia chat assistant ======================== -->
<?php if (getSetting('alia_enabled', '1') === '1'): ?>
<div class="chatbot" id="chatbot">
    <button class="chatbot-fab" id="chatbotFab" aria-label="Ask Alia" aria-expanded="false">
        <?= icon('chat', 24) ?>
        <span class="chatbot-pulse" aria-hidden="true"></span>
    </button>

    <div class="chatbot-window" id="chatbotWindow" role="dialog" aria-label="Alia chat" aria-hidden="true">
        <div class="chatbot-head">
            <div class="chatbot-id">
                <span class="chatbot-avatar">A<span class="brand-dot">.</span></span>
                <span class="chatbot-title">
                    <strong>Alia</strong>
                    <small><i class="online-dot"></i> TPT growth assistant — ask me anything</small>
                </span>
            </div>
            <button class="chatbot-close" id="chatbotClose" aria-label="Close chat"><?= icon('close', 18) ?></button>
        </div>
        <div class="chatbot-messages" id="chatbotMessages" aria-live="polite"></div>
        <div class="chatbot-input">
            <input type="text" id="chatbotInput" placeholder="Ask Alia about services, pricing, process…" autocomplete="off" aria-label="Message">
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
window.PIE = { base: <?= json_encode(BASE_URL) ?>, api: <?= json_encode(asset('chatbot-api.php')) ?> };
</script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js" defer></script>
<?php if (!empty($pageLibs['typed'])): ?><script src="https://cdn.jsdelivr.net/npm/typed.js@2.1.0/dist/typed.umd.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['particles'])): ?><script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['swiper'])): ?><script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['chart'])): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script><?php endif; ?>
<?php if (!empty($pageLibs['sortable'])): ?><script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script><?php endif; ?>
<script src="<?= asset('assets/js/main.js') ?>" defer></script>
<?php if (getSetting('alia_enabled', '1') === '1'): ?>
<script src="<?= asset('assets/js/chatbot.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>

<?php
/**
 * ---------------------------------------------------------------------------
 *  Short "Start a project" form — used inside the on-page popup.
 * ---------------------------------------------------------------------------
 *  This is deliberately SHORT (five fields). It posts to the SAME contact
 *  endpoint as the Contact Us page, with the same CSRF token, honeypot and
 *  CAPTCHA handling, so every submission still lands in the same database
 *  table, the same admin notification and the same SMTP email path.
 *
 *  Rendered by includes/footer.php only on pages where the popup is offered
 *  (set $contactModalEnabled = true before including the header).
 *
 *  Expected: $serviceOptions (array) — falls back to the standard list.
 */
if (!isset($serviceOptions) || !is_array($serviceOptions) || !$serviceOptions) {
    $serviceOptions = array('Meta Ads', 'Social Media Management', 'Google Ads', 'Digital Marketing', 'SEO', 'Local SEO', 'AI Business Optimization', 'Website Development', 'App Development', 'Graphic Design', 'Data Analytics & Reporting', 'Not Sure');
}
$quickPrefill = isset($quickPrefillService) ? (string) $quickPrefillService : '';
?>
<div id="tpt-quick-contact" class="quick-contact-host" hidden>
    <form id="quickContactForm" method="post" action="<?= url('contact') ?>" novalidate data-quick-contact>
        <?= csrfField() ?>
        <input type="text" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true" class="quick-honeypot" placeholder="Leave this empty">

        <div class="form-grid">
            <div class="field">
                <label for="qName">Full Name <span class="req">*</span></label>
                <input id="qName" name="name" type="text" required maxlength="150" autocomplete="name" placeholder="John Smith">
            </div>
            <div class="field">
                <label for="qEmail">Email Address <span class="req">*</span></label>
                <input id="qEmail" name="email" type="email" required maxlength="150" autocomplete="email" placeholder="john@example.com">
            </div>
            <div class="field">
                <label for="qPhone">Phone Number <span class="req">*</span></label>
                <input id="qPhone" name="phone" type="tel" required maxlength="30" autocomplete="tel" placeholder="+1 (555) 123-4567" data-intl-phone>
            </div>
            <div class="field">
                <label for="qService">Service <span class="req">*</span></label>
                <select id="qService" name="service" required>
                    <option value="">Select a Service</option>
                    <?php foreach ($serviceOptions as $opt): ?>
                    <option value="<?= esc($opt) ?>"<?= $opt === $quickPrefill ? ' selected' : '' ?>><?= esc($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field full">
                <label for="qMessage">What do you want to grow? <span class="req">*</span></label>
                <textarea id="qMessage" name="message" required maxlength="4000" rows="3" placeholder="A sentence or two is enough — goal, offer and timeline."></textarea>
            </div>
            <?php if (class_exists('Captcha') && Captcha::enabled()): ?>
            <div class="field full"><?= Captcha::field() ?></div>
            <?php endif; ?>
            <div class="full">
                <button class="btn btn-primary btn-lg btn-block btn-magnetic" type="submit" name="contact_submit" value="1">Send request <?= icon('send', 18) ?></button>
                <p class="quick-contact-note">Same team, same inbox — we reply within one business day. Need the longer form? <a href="<?= url('contact') ?>">Open the Contact Us page</a>.</p>
            </div>
        </div>
        <div class="form-status" role="status" aria-live="polite"></div>
    </form>

    <div class="form-success" id="quickFormSuccess">
        <span class="tick"><?= icon('check', 34) ?></span>
        <h3>Message received.</h3>
        <p>A senior strategist will reply within one business day.</p>
    </div>
</div>

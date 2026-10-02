<?php
/**
 * Compact Start a Project form. It uses the Contact Us endpoint and its shared
 * ContactController, CSRF token, honeypot, CAPTCHA verification, validation,
 * submission record and notification mail path.
 *
 * Rendered by includes/footer.php only on pages that opt into the modal.
 */
require_once BASE_PATH . '/core/Captcha.php';
?>
<div id="tpt-quick-contact" class="quick-contact-host" hidden>
    <form id="quickContactForm" method="post" action="<?= url('contact') ?>" novalidate data-quick-contact>
        <?= csrfField() ?>
        <input type="hidden" name="contact_variant" value="project_popup">
        <input type="text" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true" class="quick-honeypot" placeholder="Leave this empty">

        <div class="form-grid">
            <div class="field">
                <input id="qName" name="name" type="text" aria-label="Name / Business Name" required maxlength="150" autocomplete="name" placeholder="Your name or business name">
            </div>
            <div class="field">
                <input id="qEmail" name="email" type="email" aria-label="Email" required maxlength="150" autocomplete="email" placeholder="you@example.com">
            </div>
            <div class="field">
                <input id="qPhone" name="phone" type="tel" aria-label="Phone Number" required maxlength="30" autocomplete="tel" placeholder="+1 (555) 123-4567" data-intl-phone>
            </div>
            <div class="field full">
                <textarea id="qMessage" name="message" aria-label="Question / Query" required maxlength="4000" rows="2" placeholder="How can we help with your project?"></textarea>
            </div>
            <?php if (class_exists('Captcha') && Captcha::enabled()): ?>
            <div class="field full quick-captcha"><?= Captcha::field() ?></div>
            <?php endif; ?>
            <div class="full">
                <button class="btn btn-primary btn-block btn-magnetic" type="submit" name="contact_submit" value="1">Send your question <?= icon('send', 18) ?></button>
            </div>
        </div>
        <div class="form-status" role="status" aria-live="polite"></div>
    </form>

    <div class="form-success" id="quickFormSuccess" role="status" aria-live="polite" aria-hidden="true">
        <span class="tick"><?= icon('check', 34) ?></span>
        <h3>Message received.</h3>
        <p>A senior strategist will reply within one business day.</p>
    </div>
</div>

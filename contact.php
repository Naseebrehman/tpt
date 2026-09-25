<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/email-templates.php';

$pageTitle = 'Contact Us — Start a Project';
$metaDesc  = 'Tell us the goal and we\'ll tell you the plan. Start a project with The Pie Technologies: free strategy call, reply within 24 hours, no pressure.';
$activeNav = 'contact';

/* ---------------------------------------------------------------------------
   Submission handling — server-side validation, DB insert, emails, JSON.
   --------------------------------------------------------------------------- */
$serviceOptions = array('Meta Ads', 'Social Media', 'SEO', 'Web Development', 'Email Marketing', 'Google Ads', 'Branding', 'Not Sure');
$budgetOptions  = array('Under $500', '$500–$1000', '$1000–$2500', '$2500–$5000', '$5000+', "Let's Discuss");
$sourceOptions  = array('Google', 'Instagram', 'Facebook', 'Referral', 'LinkedIn', 'Other');

function contactJson($ok, $message, $errors = array())
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array('success' => $ok, 'message' => $message, 'errors' => $errors));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if (!validateCSRF()) {
        if ($isAjax) { contactJson(false, 'Your session expired. Please refresh the page and try again.'); }
        setFlash('err', 'Your session expired. Please try again.');
        header('Location: ' . url('contact'));
        exit;
    }

    $name    = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
    $email   = sanitize(isset($_POST['email']) ? $_POST['email'] : '');
    $phone   = sanitize(isset($_POST['phone']) ? $_POST['phone'] : '');
    $company = sanitize(isset($_POST['company']) ? $_POST['company'] : '');
    $service = sanitize(isset($_POST['service']) ? $_POST['service'] : '');
    $budget  = sanitize(isset($_POST['budget']) ? $_POST['budget'] : '');
    $message = sanitizeMultiline(isset($_POST['message']) ? $_POST['message'] : '');
    $source  = sanitize(isset($_POST['source']) ? $_POST['source'] : '');

    $errors = array();
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150)                 { $errors['name'] = 'Please enter your full name.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                     { $errors['email'] = 'Please enter a valid email address.'; }
    if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{6,30}$/', $phone))  { $errors['phone'] = 'That phone number doesn\'t look right.'; }
    if ($service !== '' && !in_array($service, $serviceOptions, true))  { $errors['service'] = 'Please pick a service from the list.'; }
    if ($budget !== '' && !in_array($budget, $budgetOptions, true))     { $errors['budget'] = 'Please pick a budget range from the list.'; }
    if ($source !== '' && !in_array($source, $sourceOptions, true))     { $errors['source'] = 'Please pick an option from the list.'; }
    if (mb_strlen($message) < 10)                                       { $errors['message'] = 'Tell us a little more (at least 10 characters).'; }

    /* simple honeypot: bots fill hidden fields */
    $honeypot = isset($_POST['website_url']) ? trim((string) $_POST['website_url']) : '';
    if ($honeypot !== '') {
        if ($isAjax) { contactJson(true, 'Thanks! We\'ll be in touch within 24 hours.'); }
        header('Location: ' . url('contact'));
        exit;
    }

    if ($errors) {
        $first = reset($errors);
        if ($isAjax) { contactJson(false, $first, $errors); }
        setFlash('err', $first);
        header('Location: ' . url('contact'));
        exit;
    }

    $submission = array(
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'company'    => $company,
        'service'    => $service,
        'budget'     => $budget,
        'message'    => $message,
        'source'     => $source,
        'status'     => 'new',
        'notes'      => '',
        'ip_address' => pieClientIp(),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
        'created_at' => date('Y-m-d H:i:s'),
    );

    $newId = dbInsert(
        'INSERT INTO contact_submissions (name, email, phone, company, service, budget, message, source, status, notes, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, "new", "", ?, ?)',
        array($name, $email, $phone, $company, $service, $budget, $message, $source, $submission['ip_address'], $submission['user_agent'])
    );

    if ($newId < 0) {
        if ($isAjax) { contactJson(false, 'We couldn\'t save your message. Please email us directly at ' . getSetting('site_email', ADMIN_EMAIL) . '.'); }
        setFlash('err', 'Something went wrong saving your message — please email us directly.');
        header('Location: ' . url('contact'));
        exit;
    }

    /* Notifications (never block the visitor if mail is misconfigured) */
    try {
        sendEmail(getSetting('site_email', ADMIN_EMAIL), 'New Contact Form Submission — ' . $name, emailAdminNotification($submission));
        sendEmail($email, 'We received your message, ' . $name, emailClientAutoReply($submission));
    } catch (Throwable $mailError) {
        error_log('[TPT] Contact mail error: ' . $mailError->getMessage());
    }

    if ($isAjax) {
        contactJson(true, 'Thanks ' . explode(' ', $name)[0] . '! Your message is with our strategy team — we\'ll reply within 24 hours.');
    }
    setFlash('ok', 'Thanks! Your message has been received — we\'ll reply within 24 hours.');
    header('Location: ' . url('contact') . '?sent=1');
    exit;
}

$sentFlash = isset($_GET['sent']) ? true : false;
$whats     = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', ''));

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Contact</p>
        <h1>Tell us the goal.<br>We&rsquo;ll bring the plan.</h1>
        <p class="lead">Fill in the form and a strategist — not a salesperson — will come back to you within 24 hours with honest next steps.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-panel" data-aos="fade-up">
                <h2>Start a project</h2>
                <p class="sub">Fields marked <span style="color:var(--violet-soft)">*</span> are required. Everything stays confidential.</p>

                <form id="contactForm" method="post" action="<?= url('contact') ?>" novalidate>
                    <?= csrfField() ?>
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px" placeholder="Leave this empty">

                    <div class="form-grid">
                        <div class="field">
                            <label for="fName">Full Name <span class="req">*</span></label>
                            <input id="fName" name="name" type="text" required maxlength="150" autocomplete="name" placeholder="Ayesha Khan">
                        </div>
                        <div class="field">
                            <label for="fEmail">Email Address <span class="req">*</span></label>
                            <input id="fEmail" name="email" type="email" required maxlength="150" autocomplete="email" placeholder="you@company.com">
                        </div>
                        <div class="field">
                            <label for="fPhone">Phone Number</label>
                            <input id="fPhone" name="phone" type="tel" maxlength="30" autocomplete="tel" placeholder="+92 300 0000000">
                        </div>
                        <div class="field">
                            <label for="fCompany">Company Name</label>
                            <input id="fCompany" name="company" type="text" maxlength="150" autocomplete="organization" placeholder="Company Ltd.">
                        </div>
                        <div class="field">
                            <label for="fService">Service Interested In</label>
                            <select id="fService" name="service">
                                <option value="">Select a service…</option>
                                <?php foreach ($serviceOptions as $opt): ?>
                                <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="fBudget">Budget Range</label>
                            <select id="fBudget" name="budget">
                                <option value="">Select a range…</option>
                                <?php foreach ($budgetOptions as $opt): ?>
                                <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field full">
                            <label for="fMessage">Project Details <span class="req">*</span></label>
                            <textarea id="fMessage" name="message" required maxlength="4000" placeholder="What are you trying to grow? What does success look like in 90 days?"></textarea>
                        </div>
                        <div class="field full">
                            <label for="fSource">How did you find us?</label>
                            <select id="fSource" name="source">
                                <option value="">Select…</option>
                                <?php foreach ($sourceOptions as $opt): ?>
                                <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="full">
                            <button class="btn btn-primary btn-lg btn-block btn-magnetic" type="submit" name="contact_submit" value="1">Send Message <?= icon('send', 18) ?></button>
                        </div>
                    </div>
                    <div class="form-status" role="status" aria-live="polite"></div>
                </form>

                <div class="form-success" id="formSuccess"<?= $sentFlash ? ' style="display:block"' : '' ?>>
                    <span class="tick"><?= icon('check', 34) ?></span>
                    <h3>Message received!</h3>
                    <p>Thanks for reaching out — your enquiry is now with our strategy team. We&rsquo;ll reply within 24 hours (usually much sooner). Keep an eye on your inbox, including the promotions tab.</p>
                    <a class="btn btn-ghost" href="<?= url('portfolio') ?>">Browse Our Work While You Wait</a>
                </div>
            </div>

            <aside class="contact-side" data-aos="fade-up" data-aos-delay="120">
                <?php if ($whats !== ''): ?>
                <a class="info-card" href="https://wa.me/<?= esc($whats) ?>?text=<?= rawurlencode('Hi! I\'d like to discuss a project with The Pie Technologies.') ?>" target="_blank" rel="noopener noreferrer" style="border-color:rgba(37,211,102,.4)">
                    <span class="icon" style="background:rgba(37,211,102,.14);border-color:rgba(37,211,102,.4);color:#25d366"><?= icon('whatsapp', 22) ?></span>
                    <span>
                        <strong>WhatsApp us directly</strong>
                        <p><?= esc(getSetting('whatsapp_number')) ?> · fastest reply, Mon–Sat</p>
                    </span>
                </a>
                <?php endif; ?>
                <div class="info-card">
                    <span class="icon"><?= icon('mail', 22) ?></span>
                    <span>
                        <strong>Email</strong>
                        <p><a href="mailto:<?= esc(getSetting('site_email', 'hello@thepietechnologies.com')) ?>"><?= esc(getSetting('site_email', 'hello@thepietechnologies.com')) ?></a></p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="icon"><?= icon('phone', 22) ?></span>
                    <span>
                        <strong>Phone</strong>
                        <p><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', getSetting('site_phone', ''))) ?>"><?= esc(getSetting('site_phone', '+1 000 000 0000')) ?></a></p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="icon"><?= icon('pin', 22) ?></span>
                    <span>
                        <strong>Studio</strong>
                        <p><?= esc(getSetting('site_address', 'Lahore, Pakistan')) ?></p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="icon"><?= icon('clock', 22) ?></span>
                    <span>
                        <strong>Business hours</strong>
                        <p>Monday – Saturday<br>9:00 – 19:00 (GMT+5)</p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="icon"><?= icon('sparkle', 22) ?></span>
                    <span>
                        <strong>Follow along</strong>
                        <p class="footer-socials" style="margin-top:10px">
                            <?php foreach (array('instagram_url' => 'instagram', 'facebook_url' => 'facebook', 'linkedin_url' => 'linkedin', 'tiktok_url' => 'tiktok', 'twitter_url' => 'twitter') as $sKey => $sIcon): $sHref = getSetting($sKey); if ($sHref !== ''): ?>
                            <a href="<?= esc($sHref) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= esc($sIcon) ?>"><?= icon($sIcon, 16) ?></a>
                            <?php endif; endforeach; ?>
                        </p>
                    </span>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/email-templates.php';

$pageTitle = 'Contact Us — Start a Project';
$metaDesc  = 'Tell us the goal and we\'ll tell you the plan. Start a project with The Pie Technologies: free strategy call, reply within 24 hours, no pressure.';
$activeNav = 'contact';

/* ---------------------------------------------------------------------------
   Submission handling — server-side validation, DB insert, emails, JSON.
   --------------------------------------------------------------------------- */
$serviceOptions = array('Meta Ads', 'Social Media Management', 'Google Ads', 'Digital Marketing', 'SEO', 'Local SEO', 'AI Business Optimization', 'Website Development', 'App Development', 'Graphic Design', 'Data Analytics & Reporting', 'Not Sure');
$budgetOptions  = array('Under $500', '$500–$1000', '$1000–$2500', '$2500–$5000', '$5000+', "Let's Discuss");
$sourceOptions  = array('Google', 'Instagram', 'Facebook', 'Referral', 'LinkedIn', 'Other');

/* Step-1 goal picker (live-site flow) mapped onto the service options above. */
$goalOptions = array(
    'Generate more leads'           => 'Meta Ads',
    'Get more customers'            => 'Digital Marketing',
    'Improve SEO'                   => 'SEO',
    'Run Meta Ads'                  => 'Meta Ads',
    'Improve social media'          => 'Social Media Management',
    'Build a website'               => 'Website Development',
    'Improve Google visibility'     => 'Local SEO',
    'Improve AI/search visibility'  => 'AI Business Optimization',
    'Automate my business'          => 'AI Business Optimization',
    'Something else'                => 'Not Sure',
);

/* ?service= prefill — service pages carry their name in the CTA link. */
$prefillService = isset($_GET['service']) ? trim((string) $_GET['service']) : '';
if (!in_array($prefillService, $serviceOptions, true)) { $prefillService = ''; }

require_once BASE_PATH . '/app/Models/Repository.php';
require_once BASE_PATH . '/app/Controllers/ContactController.php';
ContactController::handle($serviceOptions, $budgetOptions, $sourceOptions);

$sentFlash = isset($_GET['sent']) ? true : false;
$whats     = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', ''));

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Contact</p>
        <h1>Tell us what you want to grow.</h1>
        <p class="lead">No marketing jargon required. Pick a goal, leave your details, and a senior strategist replies within one business day with an honest take — even if the answer is &ldquo;you don&rsquo;t need us yet&rdquo;.</p>
        <ul class="hero-bullets" style="margin-top:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px 26px;list-style:none;padding:0">
            <li style="display:flex;gap:10px;align-items:flex-start;color:var(--muted);font-size:.95rem"><span style="color:var(--violet);flex:none;margin-top:2px"><?= icon('check', 16) ?></span><span><strong style="color:var(--text)">One reply, from a human.</strong> No nurture-spam sequence.</span></li>
            <li style="display:flex;gap:10px;align-items:flex-start;color:var(--muted);font-size:.95rem"><span style="color:var(--violet);flex:none;margin-top:2px"><?= icon('check', 16) ?></span><span><strong style="color:var(--text)">Specific next steps.</strong> What we&rsquo;d do, in what order, and why.</span></li>
            <li style="display:flex;gap:10px;align-items:flex-start;color:var(--muted);font-size:.95rem"><span style="color:var(--violet);flex:none;margin-top:2px"><?= icon('check', 16) ?></span><span><strong style="color:var(--text)">Straight numbers.</strong> If you need a quote, it&rsquo;s written and itemized.</span></li>
        </ul>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-panel" data-aos="fade-up">
                <h2>Start here</h2>
                <p class="sub">Fields marked <span style="color:var(--violet-soft)">*</span> are required. Everything stays confidential.</p>

                <form id="contactForm" method="post" action="<?= url('contact') ?>" novalidate>
                    <?= csrfField() ?>
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px" placeholder="Leave this empty">

                    <div class="goal-picker" id="goalPicker">
                        <p class="eyebrow" style="margin-bottom:12px">Step 1 — pick a goal <span class="text-muted" style="text-transform:none;letter-spacing:0;font-size:.8rem">(you can change it below)</span></p>
                        <div class="goal-grid">
                            <?php foreach ($goalOptions as $goalLabel => $goalService): ?>
                            <button type="button" class="goal-chip<?= $goalService === $prefillService ? ' active' : '' ?>" data-service="<?= esc($goalService) ?>"><?= esc($goalLabel) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <p class="eyebrow" style="margin:22px 0 12px">Step 2 — your details</p>
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
                                <option value="<?= esc($opt) ?>"<?= $opt === $prefillService ? ' selected' : '' ?>><?= esc($opt) ?></option>
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
                    <h3>Message received.</h3>
                    <p>A senior strategist will reply within one business day. Meanwhile, the Growth Library is open:</p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:6px">
                        <a class="btn btn-ghost" href="<?= url('resources') ?>">Browse the Growth Library</a>
                        <a class="btn btn-ghost" href="<?= url('portfolio') ?>">See the work</a>
                    </div>
                </div>
            </div>

            <aside class="contact-side" data-aos="fade-up" data-aos-delay="120">
                <?php if ($whats !== ''): ?>
                <a class="info-card" href="https://wa.me/<?= esc($whats) ?>?text=<?= rawurlencode('Hi! I\'d like to discuss a project with The Pie Technologies.') ?>" target="_blank" rel="noopener noreferrer" style="border-color:rgba(37,211,102,.4)">
                    <span class="info-icon" style="background:rgba(37,211,102,.14);border-color:rgba(37,211,102,.4);color:#25d366"><?= icon('whatsapp', 22) ?></span>
                    <span>
                        <strong>WhatsApp us directly</strong>
                        <p><?= esc(getSetting('whatsapp_number')) ?> · fastest reply, Mon–Sat</p>
                    </span>
                </a>
                <?php endif; ?>
                <div class="info-card">
                    <span class="info-icon"><?= icon('mail', 22) ?></span>
                    <span>
                        <strong>Email</strong>
                        <p><a href="mailto:<?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?>"><?= esc(getSetting('site_email', 'info@thepietechnologies.com')) ?></a></p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="info-icon"><?= icon('phone', 22) ?></span>
                    <span>
                        <strong>Phone</strong>
                        <p><a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', getSetting('site_phone', '+1 (213) 257 8242'))) ?>"><?= esc(getSetting('site_phone', '+1 (213) 257 8242')) ?></a></p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="info-icon"><?= icon('pin', 22) ?></span>
                    <span>
                        <strong>Two locations, one standard</strong>
                        <p><?= esc(getSetting('site_address', 'Collingswood, NJ, USA · Punjab, Pakistan')) ?></p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="info-icon"><?= icon('clock', 22) ?></span>
                    <span>
                        <strong>Business hours</strong>
                        <p>Monday – Saturday<br>9:00 – 19:00 (ET &amp; PKT coverage)</p>
                    </span>
                </div>
                <div class="info-card">
                    <span class="info-icon"><?= icon('sparkle', 22) ?></span>
                    <span>
                        <strong>Follow along</strong>
                        <p class="footer-socials" style="margin-top:10px">
                            <?php foreach (pieSocialLinks() as $social): ?>
                            <a href="<?= esc($social['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= esc($social['label']) ?>"><?= icon($social['icon'], 18) ?></a>
                            <?php endforeach; ?>
                        </p>
                    </span>
                </div>
            </aside>
        </div>
    </div>
</section>

<script>
/* Goal picker (step 1) — syncs with the service select in step 2. */
(function () {
  var picker = document.getElementById('goalPicker');
  var select = document.getElementById('fService');
  if (!picker || !select) return;
  picker.addEventListener('click', function (e) {
    var chip = e.target.closest('.goal-chip');
    if (!chip) return;
    picker.querySelectorAll('.goal-chip').forEach(function (c) { c.classList.remove('active'); c.setAttribute('aria-pressed', 'false'); });
    chip.classList.add('active');
    chip.setAttribute('aria-pressed', 'true');
    select.value = chip.getAttribute('data-service');
    var stepTwo = document.querySelector('#contactForm .form-grid');
    if (stepTwo && window.innerWidth < 900) {
      stepTwo.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    if (document.getElementById('fName')) document.getElementById('fName').focus({ preventScroll: true });
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

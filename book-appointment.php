<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Book a Free Strategy Call';
$metaDesc = 'Book a free strategy call with The Pie Technologies and choose a time that works for you.';
$activeNav = '';
$bookingUrl = 'https://calendly.com/mominalitech/book-appointment';
$bookingEmbedUrl = $bookingUrl . '?hide_gdpr_banner=1&background_color=202127&text_color=f2f3f7&primary_color=8b5cf6';

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-hero book-appointment-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; Book a Strategy Call</p>
        <div class="book-appointment-hero-grid">
            <div class="book-appointment-hero-copy">
                <p class="book-appointment-kicker"><span class="book-appointment-kicker-dot" aria-hidden="true"></span> Complimentary strategy session <span class="book-appointment-kicker-divider" aria-hidden="true">·</span> No pressure</p>
                <h1>Get a clearer path to your next stage of growth.</h1>
                <p class="lead">Bring a goal, a question, or a challenge you are working through. We will talk it over and help you identify a practical next step.</p>
                <div class="book-appointment-actions">
                    <a class="btn btn-primary" href="#appointment-calendar">Choose a time <?= icon('arrow-r', 18) ?></a>
                    <a class="btn btn-ghost" href="<?= esc($bookingUrl) ?>" target="_blank" rel="noopener noreferrer">Open Calendly <?= icon('arrow-r', 18) ?></a>
                </div>
                <div class="book-appointment-trust" aria-label="Call details">
                    <span><i aria-hidden="true"><?= icon('check', 15) ?></i> Free to book</span>
                    <span><i aria-hidden="true"><?= icon('check', 15) ?></i> No obligation</span>
                    <span><i aria-hidden="true"><?= icon('check', 15) ?></i> Practical next steps</span>
                </div>
            </div>

            <aside class="book-appointment-hero-card" aria-label="What we can cover on the call">
                <div class="book-appointment-card-top">
                    <span class="book-appointment-card-label">YOUR STRATEGY SESSION</span>
                    <span class="book-appointment-card-mark" aria-hidden="true">TPT</span>
                </div>
                <p class="book-appointment-card-title">Good strategy starts with the right questions.</p>
                <p class="book-appointment-card-copy">A focused conversation about what you want to achieve—and what could help you get there.</p>
                <ol class="book-appointment-agenda">
                    <li><span>01</span><strong>Share what you are working toward</strong></li>
                    <li><span>02</span><strong>Talk through what is getting in the way</strong></li>
                    <li><span>03</span><strong>Leave with a sensible next step</strong></li>
                </ol>
                <p class="book-appointment-card-foot"><span aria-hidden="true"></span> Free to book. No obligation.</p>
            </aside>
        </div>
    </div>
</section>

<section class="section book-appointment-section" aria-labelledby="appointmentHeading">
    <div class="container">
        <div class="book-appointment-section-head">
            <div>
                <p class="eyebrow">Find a time</p>
                <h2 id="appointmentHeading">Let’s find a time to talk.</h2>
                <p>Choose an available time in the calendar. If the calendar does not load, you can open the booking page directly on Calendly.</p>
            </div>
            <a class="btn btn-ghost book-appointment-direct" href="<?= esc($bookingUrl) ?>" target="_blank" rel="noopener noreferrer">Book a Free Strategy Call <?= icon('arrow-r', 18) ?></a>
        </div>

        <div class="book-appointment-grid">
            <div class="book-appointment-calendar" id="appointment-calendar">
                <div class="book-appointment-calendar-head">
                    <div class="book-appointment-calendar-title">
                        <span class="book-appointment-calendar-dot" aria-hidden="true"></span>
                        <div>
                            <p class="book-appointment-calendar-label">CALENDLY BOOKING</p>
                            <h3>Choose your time</h3>
                        </div>
                    </div>
                    <a href="<?= esc($bookingUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Open the booking calendar in Calendly">
                        Open in Calendly <?= icon('arrow-r', 16) ?>
                    </a>
                </div>
                <div class="book-appointment-embed">
                    <iframe
                        src="<?= esc($bookingEmbedUrl) ?>"
                        title="Book a free strategy call with The Pie Technologies"
                        loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                    ></iframe>
                </div>
                <p class="book-appointment-fallback">Having trouble with the calendar? <a href="<?= esc($bookingUrl) ?>" target="_blank" rel="noopener noreferrer">Book a Free Strategy Call on Calendly</a>.</p>
            </div>

            <aside class="book-appointment-info">
                <p class="eyebrow">Before you book</p>
                <h3>Come as you are.</h3>
                <p class="book-appointment-info-copy">No deck or preparation needed. Just bring whatever is on your mind—we will take it from there.</p>
                <ul class="book-appointment-points">
                    <li><span>01</span><p>Tell us what you would like to improve or grow.</p></li>
                    <li><span>02</span><p>Ask the questions you need answered.</p></li>
                    <li><span>03</span><p>Get an honest view of possible next steps.</p></li>
                </ul>
                <div class="book-appointment-help">
                    <p>Want to ask us something first?</p>
                    <a href="<?= url('contact') ?>">Talk to our team <?= icon('arrow-r', 16) ?></a>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

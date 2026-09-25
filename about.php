<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'About Us — The Team Behind The Pie Technologies';
$metaDesc  = 'The Pie Technologies is a full-service growth agency built on one belief: marketing should be measured in revenue. Meet the team, the mission and the numbers.';
$activeNav = 'about';

$team = getTeamMembers();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; About</p>
        <h1>Built to Help Brands Win Online.</h1>
        <p class="lead">We&rsquo;re a senior-only team of strategists, media buyers, designers and engineers who got tired of watching good brands get bad marketing.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <p class="story-copy" data-aos="fade-up">
            The Pie Technologies started in a one-room office with a single client, a laptop and a rule we still keep: <span class="hl">if we can&rsquo;t measure it, we don&rsquo;t sell it.</span>
            That first account tripled its revenue in five months, and the referral from it built our second year of business.
            Today we run paid media, SEO, social, web and email for brands across four continents — still senior-only, still allergic to vanity metrics.
            We&rsquo;re not trying to be the biggest agency in the room; we&rsquo;re trying to be the one your competitors wish they&rsquo;d hired first.
        </p>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container">
        <div class="mvv-grid">
            <article class="mvv-card" data-aos="fade-up">
                <span class="icon"><?= icon('target', 24) ?></span>
                <h3>Mission</h3>
                <p>Make world-class growth marketing accessible to ambitious brands — with transparent reporting, senior talent on every account and zero locked-in contracts holding you hostage.</p>
            </article>
            <article class="mvv-card" data-aos="fade-up" data-aos-delay="100">
                <span class="icon"><?= icon('eye', 24) ?></span>
                <h3>Vision</h3>
                <p>A marketing industry where agencies are paid on outcomes, clients understand exactly what they&rsquo;re buying, and "trust me" is never a strategy.</p>
            </article>
            <article class="mvv-card" data-aos="fade-up" data-aos-delay="200">
                <span class="icon"><?= icon('shield', 24) ?></span>
                <h3>Values</h3>
                <p>Radical transparency in reporting. Speed as a feature. Craft over volume. And the discipline to tell a client "don&rsquo;t spend that" when the numbers say so.</p>
            </article>
        </div>
    </div>
</section>

<?php if ($team): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">The Team</p>
            <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Senior people on every account.</h2>
        </div>
        <div class="team-grid">
            <?php foreach ($team as $i => $member): ?>
            <article class="team-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 90 ?>">
                <div class="team-photo">
                    <?php $memberPhoto = ($member['photo'] !== '' && is_file(BASE_PATH . '/' . $member['photo'])) ? $member['photo'] : 'assets/images/placeholder.svg'; ?>
                    <img src="<?= asset($memberPhoto) ?>" alt="<?= esc($member['name']) ?>, <?= esc($member['role']) ?>" loading="lazy">
                </div>
                <div class="team-body">
                    <div>
                        <strong><?= esc($member['name']) ?></strong>
                        <span><?= esc($member['role']) ?></span>
                        <?php if (!empty($member['bio'])): ?><p><?= esc($member['bio']) ?></p><?php endif; ?>
                    </div>
                    <div class="team-socials">
                        <?php if (!empty($member['linkedin'])): ?><a href="<?= esc($member['linkedin']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= esc($member['name']) ?> on LinkedIn"><?= icon('linkedin', 15) ?></a><?php endif; ?>
                        <?php if (!empty($member['twitter'])): ?><a href="<?= esc($member['twitter']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= esc($member['name']) ?> on Twitter"><?= icon('twitter', 15) ?></a><?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section" style="padding-top:0">
    <div class="container">
        <div class="result-stats">
            <div class="result-stat" data-aos="fade-up"><strong><span data-countup="50">0</span><span class="suffix">+</span></strong><span>Clients served worldwide</span></div>
            <div class="result-stat" data-aos="fade-up" data-aos-delay="80"><strong><span data-countup="120">0</span><span class="suffix">+</span></strong><span>Campaigns launched &amp; scaled</span></div>
            <div class="result-stat" data-aos="fade-up" data-aos-delay="160"><strong><span data-countup="5">0</span><span class="suffix">+</span></strong><span>Years in the game</span></div>
            <div class="result-stat" data-aos="fade-up" data-aos-delay="240"><strong><span data-countup="98">0</span><span class="suffix">%</span></strong><span>Client retention rate</span></div>
        </div>
    </div>
</section>

<section class="final-cta">
    <div class="container">
        <h2 data-aos="fade-up">Let&rsquo;s make your brand the next success story.</h2>
        <p data-aos="fade-up" data-aos-delay="80">Thirty minutes on a call is enough to know if we&rsquo;re the right team for your goals.</p>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Talk to Us <?= icon('arrow-r', 18) ?></a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

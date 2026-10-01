<?php
require_once __DIR__ . '/includes/init.php';

$pageTitle = 'About TPT — The Agency That Thinks in Systems';
$metaDesc  = 'The Pie Technologies exists because we watched too many good businesses buy marketing in fragments — and get fragmented results. We build the whole machine instead.';
$activeNav = 'about';

$team = getTeamMembers();

$principles = array(
    array('num' => '01', 'title' => 'Systems over services',   'text' => 'A landing page, an ad and a follow-up email are not three deliverables. They are one thing with three interfaces — designed, built and measured together.'),
    array('num' => '02', 'title' => 'Evidence over opinion',    'text' => 'When the data disagrees with our taste, the data wins. Every test has a hypothesis, every winner has a number, every loser gets retired without ceremony.'),
    array('num' => '03', 'title' => 'Radical ownership',        'text' => 'Your accounts, your data, your code, your creative — registered to you from day one and leaving with you if we ever part ways. No hostage situations.'),
    array('num' => '04', 'title' => 'No theater metrics',       'text' => 'Impressions and likes are inputs, not outcomes. We talk leads, quality, cost and revenue — the four numbers a business actually runs on.'),
    array('num' => '05', 'title' => 'Say the hard thing early', 'text' => 'Saturated market, weak offer, a website capping every channel — you hear it in week one, not month six. An honest “not yet” beats an expensive “maybe”.'),
    array('num' => '06', 'title' => 'Small loops, compounding', 'text' => 'Weekly testing beats quarterly master plans. We optimize for learning speed, because the team that learns fastest in your market wins your market.'),
);

$marketingPhilosophy = array(
    'Demand is captured AND created — plan and budget for both, deliberately.',
    'Creative is the biggest performance lever in paid media today.',
    'Speed-to-lead is a marketing metric, not a sales problem.',
    'Retention math beats acquisition vanity, every quarter.',
    'A month of structured learning beats a year of guessing.',
);
$technologyPhilosophy = array(
    'Boring, proven stacks for business systems — exciting technology is for products, not infrastructure.',
    'Speed and security are features, not chores to defer.',
    'Everything measurable, or it doesn’t ship.',
    'Clients own their infrastructure, always.',
    'The simplest build that solves the whole problem — no résumé-driven architecture.',
);

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <p class="eyebrow crumbs"><a href="<?= url('') ?>">Home</a> &nbsp;/&nbsp; About TPT</p>
        <h1>The agency that thinks in systems.</h1>
        <p class="lead">The Pie Technologies exists because we watched too many good businesses buy marketing in fragments — and get fragmented results. We build the whole machine instead.</p>
    </div>
</section>

<!-- ============================= WHO WE ARE ============================= -->
<section class="section">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Who we are</p>
            <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Strategists, buyers, SEOs, designers &amp; engineers. One table.</h2>
        </div>
        <p class="story-copy" data-aos="fade-up">
            TPT is a digital growth and technology team based in <span class="hl">Collingswood, New Jersey, USA</span> — working remotely with clients across time zones so the work keeps moving. Our background spans software development and digital marketing, which is why our campaigns ship with tracking that works and our websites ship with positioning that sells.
        </p>
        <p class="story-copy" data-aos="fade-up">
            And the name? <span class="hl">Everyone wants a slice of growth. We bake the whole pie</span> — crust to filling: strategy underneath, data holding it together.
        </p>
    </div>
</section>

<!-- ========================= OPERATING PRINCIPLES ======================= -->
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">Operating principles</p>
            <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Six rules we don&rsquo;t bend.</h2>
        </div>
        <div class="feature-grid">
            <?php foreach ($principles as $pi => $principle): ?>
            <article class="feature-card" data-aos="fade-up" data-aos-delay="<?= ($pi % 3) * 90 ?>">
                <span class="mono" style="color:#2c2c36;font-size:1.6rem;font-weight:700"><?= esc($principle['num']) ?></span>
                <h3><?= esc($principle['title']) ?></h3>
                <p><?= esc($principle['text']) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================ PHILOSOPHIES ============================ -->
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head center" data-aos="fade-up">
            <p class="eyebrow">Two philosophies</p>
            <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Marketing and technology stopped being separable years ago.</h2>
            <p class="section-lead">Your ads are software — tracking, feeds, APIs. Your website is marketing — positioning, persuasion, proof. AI search is both at once.</p>
        </div>
        <div class="split" style="align-items:start">
            <div class="chart-card" data-aos="fade-up">
                <p class="eyebrow" style="margin-bottom:14px"><?= icon('megaphone', 16) ?> How we think about marketing</p>
                <ul style="display:grid;gap:12px;list-style:none;padding:0">
                    <?php foreach ($marketingPhilosophy as $mp): ?>
                    <li style="display:flex;gap:10px;align-items:flex-start;color:var(--muted);font-size:.92rem;line-height:1.65"><span style="color:var(--violet-soft);flex:none;margin-top:2px"><?= icon('check', 15) ?></span><span><?= esc($mp) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="chart-card" data-aos="fade-up" data-aos-delay="120">
                <p class="eyebrow" style="margin-bottom:14px"><?= icon('code', 16) ?> How we think about technology</p>
                <ul style="display:grid;gap:12px;list-style:none;padding:0">
                    <?php foreach ($technologyPhilosophy as $tp): ?>
                    <li style="display:flex;gap:10px;align-items:flex-start;color:var(--muted);font-size:.92rem;line-height:1.65"><span style="color:var(--violet-soft);flex:none;margin-top:2px"><?= icon('check', 15) ?></span><span><?= esc($tp) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- =============================== MISSION ============================== -->
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="founder-card mission-card" data-aos="fade-up">
            <div class="founder-copy" style="max-width:none">
                <p class="eyebrow">Mission</p>
                <h2 style="font-size:clamp(1.5rem,3vw,2.2rem);margin-top:10px">&ldquo;Give serious businesses a single accountable team for digital growth — so nobody ever again has to project-manage five vendors who &lsquo;did their part&rsquo;.&rdquo;</h2>
                <div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap">
                    <a href="<?= url('contact') ?>" class="btn btn-primary btn-magnetic">Work with us <?= icon('arrow-r', 18) ?></a>
                    <a href="<?= url('portfolio') ?>" class="btn btn-ghost btn-magnetic">See the work</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================================ TEAM ================================ -->
<?php if ($team): ?>
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="section-head" data-aos="fade-up">
            <p class="eyebrow">The Team</p>
            <h2 class="section-title" style="font-size:clamp(1.8rem,3.6vw,2.8rem)">Senior people on every account.</h2>
        </div>
        <?php if (!empty($team[0]['is_preview'])): ?><p class="team-preview-note">Team preview using the project’s starter profiles. Update names, bios and photos in Admin → Team before publishing.</p><?php endif; ?>
        <div class="team-grid">
            <?php foreach ($team as $i => $member): ?>
            <article class="team-card" data-aos="fade-up" data-aos-delay="<?= ($i % 3) * 90 ?>">
                <div class="team-photo">
                    <?php
                    $memberPhoto = !empty($member['photo']) && is_file(BASE_PATH . '/' . $member['photo']) ? $member['photo'] : '';
                    if ($memberPhoto === '' && stripos($member['role'], 'founder') !== false) { $memberPhoto = 'assets/images/founder-avatar.jpg'; }
                    $initials = '';
                    foreach (array_slice(preg_split('/\s+/', trim($member['name'])), 0, 2) as $part) { $initials .= mb_substr($part, 0, 1); }
                    ?>
                    <?php if ($memberPhoto !== ''): ?>
                    <img src="<?= asset($memberPhoto) ?>" alt="<?= esc($member['name']) ?>, <?= esc($member['role']) ?>" width="88" height="88" loading="lazy">
                    <?php else: ?>
                    <span class="team-initials" aria-hidden="true"><?= esc(mb_strtoupper($initials)) ?></span>
                    <?php endif; ?>
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

<section class="final-cta">
    <div class="container">
        <h2 data-aos="fade-up">Let&rsquo;s build your system.</h2>
        <p data-aos="fade-up" data-aos-delay="80">One call is enough to know if we&rsquo;re the right team for your goals — and if we&rsquo;re not, we&rsquo;ll tell you who or what is.</p>
        <div class="hero-ctas" data-aos="fade-up" data-aos-delay="140">
            <a href="<?= url('contact') ?>" class="btn btn-primary btn-lg btn-magnetic">Work with us <?= icon('arrow-r', 18) ?></a>
            <a href="<?= url('portfolio') ?>" class="btn btn-ghost btn-lg btn-magnetic">See the work</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

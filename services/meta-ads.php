<?php
/**
 * The Pie Technologies — Meta Ads service page
 * NOTE: by brand rule this page never shows pricing or price-implying
 * package names. Outcomes and process only.
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'meta-ads',
    'title' => 'Turn Every Scroll Into a Sale.',
    'lead'  => 'Full-funnel Facebook & Instagram advertising built around one scoreboard: your return on ad spend. Strategy, creative, testing and scaling — handled end to end.',
    'seoTitle' => 'Meta Ads Management Agency | Facebook & Instagram Ads That Convert',
    'seoDesc'  => 'We plan, build and scale Meta Ads campaigns for eCommerce, local and service businesses. Lead gen, retargeting, creative strategy and ROAS optimisation — no fluff.',
    'intro'   => array(
        'heading'    => 'What we do',
        'title'      => 'Paid social, run like a P&L.',
        'paragraphs' => array(
            'Most agencies buy impressions. We buy outcomes. Every campaign we launch is wired to a revenue event — a purchase, a booked call, a qualified lead — and managed against the cost of getting it.',
            'That means full-funnel structure: cold audiences built from real signal, retargeting that follows intent instead of haunting people, and creative tested on a cadence that keeps fatigue away. You see every number. We answer for every number.',
        ),
        'features' => array(
            array('icon' => 'target',  'title' => 'Lead Generation',      'text' => 'Instant forms and landing-page funnels that fill your pipeline with qualified, contactable leads.'),
            array('icon' => 'zap',     'title' => 'Retargeting',          'text' => 'Sequential retargeting that moves warm audiences forward instead of spamming the same ad twice.'),
            array('icon' => 'pen',     'title' => 'Creative Strategy',    'text' => 'Hook-first ad concepts, UGC direction and static/Reels production built for the feed, not the boardroom.'),
            array('icon' => 'filter',  'title' => 'A/B Testing',          'text' => 'Structured tests on hooks, angles, audiences and offers — with a weekly read-out of what won and why.'),
            array('icon' => 'users',   'title' => 'Lookalike Audiences',  'text' => 'Seed audiences built from your best customers, so Meta finds more people exactly like them.'),
            array('icon' => 'chart',   'title' => 'ROAS Optimization',    'text' => 'Budget shifted daily toward the ads, audiences and placements that actually print money.'),
        ),
    ),
    'platformsTitle' => 'One account. Every Meta surface.',
    'platforms' => array(
        array('icon' => 'facebook',  'label' => 'Facebook'),
        array('icon' => 'instagram', 'label' => 'Instagram'),
        array('icon' => 'whatsapp',  'label' => 'WhatsApp'),
        array('icon' => 'chat',      'label' => 'Messenger'),
        array('icon' => 'play',      'label' => 'Reels'),
    ),
    'accordion' => array(
        'heading' => 'Campaign types we run.',
        'items'   => array(
            array('title' => 'Lead Generation',  'body' => 'Instant-form and landing-page campaigns optimised on cost per qualified lead — not cost per click. We qualify inside the form so your sales team never chases tyre-kickers.'),
            array('title' => 'Traffic',          'body' => 'Cheap, clean, intent-filtered traffic to your site or offer — used deliberately as a top-of-funnel feeder for retargeting pools, never as a vanity metric.'),
            array('title' => 'Conversions',      'body' => 'Purchase and lead-event campaigns with server-side tracking (CAPI) so bidding works even after iOS privacy changes. This is where the revenue lives.'),
            array('title' => 'Brand Awareness',  'body' => 'Reach and frequency-bought campaigns for launches and new markets — measured on recall lifts and branded search volume, not just impressions.'),
            array('title' => 'Retargeting',      'body' => 'Dynamic product ads, view-content sequences and offer-led warm campaigns that recover the 97% who didn\'t buy the first time.'),
            array('title' => 'Catalogue',        'body' => 'Advantage+ catalogue campaigns feeding your full product set into Meta\'s delivery system, with feed hygiene and margin-aware exclusions handled by us.'),
            array('title' => 'Engagement',       'body' => 'Reels and video-view campaigns that build cheap, warm audiences and social proof — then hand them to your conversion campaigns at a discount.'),
        ),
    ),
    'steps' => array(
        array('title' => 'Research',   'text' => 'Account, pixel, competitor and offer audit. We find the leaks before we spend a riyal.'),
        array('title' => 'Strategy',   'text' => 'Funnel architecture, audience map, budget split and the KPI each campaign is hired to hit.'),
        array('title' => 'Creative',   'text' => 'Hooks, scripts, statics and Reels produced in batches — built to be tested, not admired.'),
        array('title' => 'Launch',     'text' => 'Clean tracking (pixel + CAPI), structured campaigns, negative audiences and spend caps from day one.'),
        array('title' => 'Optimize',   'text' => 'Daily reads, weekly test cycles, budget migration to winners and ruthless kills of losers.'),
        array('title' => 'Scale',      'text' => 'Vertical and horizontal scaling — new angles, new geos, new placements — without breaking CPA.'),
    ),
    'stats' => array(
        array('value' => 4.2, 'decimals' => 1, 'suffix' => '×', 'label' => 'Average ROAS across managed accounts'),
        array('value' => 68,  'suffix' => '%', 'label' => 'Lower cost per lead after 90 days'),
        array('value' => 120, 'suffix' => '+', 'label' => 'Meta campaigns launched and scaled'),
    ),
    'who' => array(
        array('icon' => 'grid', 'title' => 'eCommerce Brands',      'text' => 'Catalogue owners who need profitable acquisition and a retargeting engine that recovers abandoned carts every single day.'),
        array('icon' => 'pin',  'title' => 'Local Businesses',      'text' => 'Clinics, restaurants, gyms and studios that need a steady stream of booked appointments from a 10 km radius.'),
        array('icon' => 'users','title' => 'Service Businesses',    'text' => 'Agencies, consultants and B2B teams that need qualified calls on the calendar — not a folder full of cold clicks.'),
    ),
    'testimonial' => true,
    'faq' => array(
        array('q' => 'How much ad budget do I need to start?', 'a' => 'Meta needs enough conversion data to exit the learning phase — for most accounts that means a budget that can buy 30–50 conversion events per campaign per month. On a strategy call we\'ll reverse-engineer the number from your average order value or lead value, so you never guess.'),
        array('q' => 'How fast will I see results?', 'a' => 'Tracking and structure are fixed in week one. Early signal (CTR, CPC, hook rates) appears in the first 7–14 days. Stable cost-per-result usually lands between weeks 3 and 6, and scaling decisions start once a campaign has 50+ conversions of history.'),
        array('q' => 'Do you produce the ad creative?', 'a' => 'Yes. Creative strategy, copywriting, static design and short-form video editing are all in-house. We ship test batches on a fixed cadence so there is always fresh ammunition in the account.'),
        array('q' => 'Who owns the ad account?', 'a' => 'You do — always. We work inside your Business Manager with partner access. If we ever part ways, every campaign, pixel and audience stays with you. No hostage-taking, ever.'),
        array('q' => 'What about iOS tracking and lost data?', 'a' => 'We implement the Conversions API server-side plus clean UTM and modelling-friendly event deduplication, so bidding still works with partial signal. Your reported numbers get reconciled against real revenue, not just Ads Manager.'),
        array('q' => 'Do you guarantee results?', 'a' => 'Anyone who guarantees a ROAS number is selling you fiction. What we guarantee is the system: full-funnel structure, weekly testing cadence, transparent reporting and a team that treats your budget like its own P&L.'),
    ),
    'cta' => array(
        'title'  => 'Get a Free Meta Ads Audit.',
        'text'   => 'We\'ll tear down your current account (or build your first one) and show you exactly where the money is leaking — free, no strings.',
        'button' => 'Get My Free Audit',
    ),
);

require dirname(__DIR__) . '/includes/service-page.php';

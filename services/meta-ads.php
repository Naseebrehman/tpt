<?php
/**
 * The Pie Technologies — Meta Ads service page (GROW)
 * NOTE: by brand rule this page never shows pricing or price-implying
 * package names, and never claims fabricated performance numbers.
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'meta-ads',
    'title' => 'Meta Ads',
    'lead'  => 'Facebook & Instagram campaigns engineered to turn attention into pipeline.',
    'seoTitle' => 'Meta Ads Management — Facebook & Instagram Advertising | TPT',
    'seoDesc'  => 'Campaign architecture, audience strategy, creative testing, retargeting and server-side conversion tracking for Facebook & Instagram — managed against cost per result, not clicks.',
    'heroDesc' => 'Attention is cheap to buy and easy to waste. We build Meta campaigns the way we build software: architecture first, clean tracking, structured creative tests, and budget that moves toward evidence — every week.',
    'bullets'  => array(
        'One offer per campaign, one job per ad set',
        'Creative testing on a weekly rhythm',
        'Pixel + Conversions API tracking that survives iOS',
        'Cost per qualified lead as the scoreboard',
    ),
    'cta' => array(
        'title'  => 'Spending on Meta but can’t say what it produced?',
        'text'   => 'Get a Meta Ads audit: account structure, tracking integrity, creative fatigue and where the budget is actually going — with the leaks marked. You’ll get a straight answer, even if it’s “pause and fix first.”',
        'button' => 'Get a Meta Ads Audit',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'Boosting is not a media strategy.',
        'paragraphs' => array(
            'Most Meta accounts we inherit look the same: boosted posts, a dozen overlapping audiences, a pixel that fired once in 2022, and creative chosen by whoever shouted last. Spend goes out; enquiries come back sporadic, unqualified and untraceable to any ad.',
            'The platform isn’t the problem. Meta’s delivery system is extraordinary — when it’s fed clean signals. Give it muddy events, muddled offers and fatigued creative, and it will efficiently buy you nothing.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Leads arrive, but sales says they’re cold or unreachable',
            'Nobody can tell which ad or audience produced which enquiry',
            'Cost per result creeps up every month until the account is paused',
            'The same three ads have been running since launch',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From scroll to signed.',
        'lead'    => 'A funnel, not a feed. Every stage has one job and one number.',
        'steps'   => array(
            array('title' => 'Signal',   'text' => 'Pixel + Conversions API wired to real outcomes — leads, calls, purchases — before a dollar is spent.'),
            array('title' => 'Offer',    'text' => 'One campaign, one offer, one audience temperature. Clarity the delivery algorithm can actually optimize against.'),
            array('title' => 'Creative', 'text' => 'Hooks, angles and formats produced in batches — built to be tested, not admired.'),
            array('title' => 'Capture',  'text' => 'A landing page or instant form that matches the ad’s promise and qualifies before it collects.'),
            array('title' => 'Follow-up','text' => 'Speed-to-lead under five minutes: routing, SMS/email sequences and retargeting for the not-yet-ready.'),
            array('title' => 'Scale',    'text' => 'Winners get budget, new angles and new audiences. Losers get retired early and cheaply.'),
        ),
        'note' => 'Creative is the biggest performance lever on Meta today. We treat it like a testing program, because it is one.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The Meta Ads operating stack.',
    'pillarsLead'    => 'Four workstreams, run continuously:',
    'pillars' => array(
        array('title' => 'Architecture & audiences', 'text' => 'Account structure that keeps signals clean and scaling predictable.', 'points' => array(
            'Campaigns split by audience temperature: cold, warm, hot',
            'Broad + interest + lookalike stacks tested against each other',
            'Consolidated ad sets — enough budget per cell to exit learning',
            'Exclusions and frequency caps so retargeting never haunts',
        )),
        array('title' => 'Creative & testing', 'text' => 'A weekly testing matrix: hook × format × angle.', 'points' => array(
            'Hook-first statics, UGC-style video, carousels and Reels cuts',
            'Kill rules and budgets defined before tests launch',
            'Fatigue monitoring by frequency and first-time impression ratio',
            'Winning angles briefed back into the next creative batch',
        )),
        array('title' => 'Tracking & attribution', 'text' => 'Measurement that survives privacy changes and platform wobbles.', 'points' => array(
            'Meta Pixel plus server-side Conversions API with deduplication',
            'UTM discipline and offline conversions where sales close offline',
            'Lead-quality feedback loop from your CRM back into optimization',
            'Honest reporting: platform numbers vs. actual pipeline',
        )),
        array('title' => 'Landing & conversion', 'text' => 'The click is halfway; the page is the other half.', 'points' => array(
            'Purpose-built landing pages per campaign, matched to the ad',
            'Forms that qualify — budget, timing, fit — before they collect',
            'Instant forms with follow-up automation for speed-to-lead',
            'Page speed and mobile UX treated as media-buying variables',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'The first ninety days.',
    'timeline' => array(
        array('when' => 'Week 1–2', 'title' => 'Audit & baseline',   'text' => 'Account, pixel, offer and competitor teardown. Historical numbers reconciled against CRM reality. Baselines agreed in writing.'),
        array('when' => 'Week 2–3', 'title' => 'Tracking rebuild',   'text' => 'Pixel + CAPI verified, events mapped to real outcomes, UTM scheme published, dashboards wired.'),
        array('when' => 'Week 3–4', 'title' => 'Architecture & creative', 'text' => 'Campaign structure built by temperature; first creative batch briefed, produced and QA’d against the hooks matrix.'),
        array('when' => 'Week 5–6', 'title' => 'Launch',             'text' => 'Staged go-live with spend caps. Daily delivery checks; weekly test cycles begin. Landing pages tuned against behavior.'),
        array('when' => 'Week 7–10','title' => 'Optimize',           'text' => 'Budget migrates to winning cells; losing angles retired. Lead-quality loop with your sales team tightens targeting and forms.'),
        array('when' => 'Week 11–13','title' => 'Scale',             'text' => 'New angles, audiences and placements added on evidence. Monthly review with real numbers and the next quarter’s plan.'),
    ),
    'faqTitle' => 'Meta Ads, straight answers.',
    'faq' => array(
        array('q' => 'How much ad spend do we need to start?', 'a' => 'Enough to buy statistically meaningful tests in your market — that number differs wildly between, say, roofing leads in New Jersey and e-commerce in the Gulf. We model it from your price point and target cost per result before you commit, and we tell you honestly if your budget can’t support the channel yet.'),
        array('q' => 'Do we need a big existing audience or email list?', 'a' => 'No. Cold-audience prospecting is where most lead-gen budgets live anyway. Existing customers, site visitors and engagement data help — they seed lookalikes and retargeting — but we build the signal layer from scratch when needed.'),
        array('q' => 'Who owns the ad account and pixel?', 'a' => 'You do, always. We work as a partner inside your Business Manager. If we ever part ways, campaigns, audiences, pixel history and creative files stay with you — no hostage situations.'),
        array('q' => 'How is this different from boosting posts ourselves?', 'a' => 'Boosting optimizes for engagement; we optimize for a business event — a qualified lead, a booked call, a purchase — with server-side tracking, structured tests and landing pages built to convert. Different machinery, different scoreboard.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint', 'title' => 'The Meta Ads Lead Generation Blueprint', 'note' => 'The exact 90-day framework, free', 'url' => url('resources/meta-ads-lead-generation-blueprint')),
        array('label' => 'Playbook',  'title' => 'Meta Ads Creative Testing Playbook',     'note' => 'Hook × format × angle, with kill rules', 'url' => url('resources/meta-ads-creative-testing-playbook')),
        array('label' => 'Case study','title' => 'Lead Generation Engine',                 'note' => 'Meta Ads × landing pages, end to end', 'url' => url('portfolio/lead-generation-engine-meta-ads')),
    ),
    'deliverables' => array(
        'Campaign architecture by audience temperature',
        'Pixel + Conversions API implementation and event mapping',
        'Creative testing matrix with weekly production batches',
        'Audience research, lookalike seeding and exclusion logic',
        'Purpose-built landing pages or qualified instant forms',
        'Lead routing and speed-to-lead follow-up automation',
        'Weekly optimization and monthly performance reporting',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('graphic-design', 'web-development', 'data-analytics'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

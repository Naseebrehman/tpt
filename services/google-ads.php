<?php
/**
 * The Pie Technologies — Google Ads service page
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'google-ads',
    'title' => 'Be the Answer at the Exact Moment Intent Appears.',
    'lead'  => 'Search, Shopping, Performance Max and YouTube campaigns built on tight intent mapping and ruthless negative-keyword discipline. High-intent traffic, measured to the last click.',
    'seoTitle' => 'Google Ads Management Agency | Search, Shopping & PMax',
    'seoDesc'  => 'Google Ads managed properly: search, Performance Max, Shopping, YouTube and display — with conversion tracking, negative keyword discipline and transparent ROAS reporting.',
    'intro'   => array(
        'heading'    => 'What we do',
        'title'      => 'Intent is the cheapest traffic on earth.',
        'paragraphs' => array(
            'Someone typing "buy running shoes lahore delivery" is not browsing — they\'re shopping. Google Ads puts you in front of that moment. Our job is to win it at a cost that makes sense for your margins.',
            'That means surgical account structure, ad copy that pre-qualifies the click, landing pages that continue the promise, and a negative-keyword list we treat as a living document. Wasted spend is a bug we hunt daily.',
        ),
        'features' => array(
            array('icon' => 'search',  'title' => 'Search Campaigns',     'text' => 'Tightly themed ad groups, RSA copy tested per intent tier and bids managed against CPA — not average position.'),
            array('icon' => 'sparkle', 'title' => 'Performance Max',      'text' => 'PMax fed with good assets, clean audiences and brand exclusions — so Google\'s automation works for you, not against you.'),
            array('icon' => 'grid',    'title' => 'Shopping / Merchant',  'text' => 'Feed optimisation, title engineering and margin-aware bidding for product advertisers.'),
            array('icon' => 'youtube', 'title' => 'YouTube Ads',          'text' => 'Skippable in-stream and Shorts campaigns that build demand at CPMs social can\'t touch.'),
            array('icon' => 'globe',   'title' => 'Display & Remarketing','text' => 'Audience-led display for reach and sequential remarketing that closes the loop on warm traffic.'),
            array('icon' => 'target',  'title' => 'Landing Page CRO',     'text' => 'Message-matched landing pages and form friction audits, because the click is only half the job.'),
        ),
    ),
    'platformsTitle' => 'Every Google surface that carries intent.',
    'platforms' => array(
        array('icon' => 'search',  'label' => 'Search'),
        array('icon' => 'grid',    'label' => 'Shopping'),
        array('icon' => 'sparkle', 'label' => 'Performance Max'),
        array('icon' => 'youtube', 'label' => 'YouTube'),
        array('icon' => 'globe',   'label' => 'Display'),
    ),
    'steps' => array(
        array('title' => 'Research',  'text' => 'Keyword intent mapping, competitor ad analysis and margin maths per product or service.'),
        array('title' => 'Structure', 'text' => 'Account architecture by intent tier, with conversion tracking and enhanced conversions verified first.'),
        array('title' => 'Copy',      'text' => 'RSAs and assets written to pre-qualify clicks — every headline earns its place.'),
        array('title' => 'Launch',    'text' => 'Campaigns go live with budgets, negatives and bid strategies matched to data volume.'),
        array('title' => 'Optimize',  'text' => 'Search-term mining weekly, bid and budget shifts daily, creatives refreshed on fatigue signals.'),
        array('title' => 'Scale',     'text' => 'New intent tiers, geos and PMax asset groups added only when core CPA holds.'),
    ),
    'stats' => array(
        array('value' => 5.6, 'decimals' => 1, 'suffix' => '×', 'label' => 'Average ROAS on managed search accounts'),
        array('value' => 41,  'suffix' => '%', 'label' => 'Average reduction in cost per acquisition'),
        array('value' => 24,  'suffix' => '/7', 'label' => 'Intent captured — your ads never sleep'),
    ),
    'who' => array(
        array('icon' => 'grid',  'title' => 'E-commerce Stores',   'text' => 'Shopping and PMax operators who need margin-aware bidding and a feed that actually converts.'),
        array('icon' => 'users', 'title' => 'Service & B2B',       'text' => 'High-consideration sellers who need qualified enquiries from search intent, tracked all the way to closed revenue.'),
        array('icon' => 'pin',   'title' => 'Local & Multi-Location', 'text' => 'Brands that need to own their city\'s searches and map-adjacent intent without paying national CPCs.'),
    ),
    'testimonial' => true,
    'faq' => array(
        array('q' => 'Google Ads or Meta Ads — where should my budget go?', 'a' => 'Google captures existing demand; Meta creates it. If people already search for what you sell, Google converts fastest. If your category needs education or impulse, Meta wins. Most scaling brands run both — we\'ll tell you the right split for your margins on a call.'),
        array('q' => 'How much should I budget for Google Ads?', 'a' => 'Start from your economics, not from a guess: target CPA × the conversions you need per month, plus enough click volume for Smart Bidding to learn (roughly 30 conversions/month per campaign). We\'ll model it with you before you spend anything.'),
        array('q' => 'Why is my current account wasting budget?', 'a' => 'The classic leaks: broad match without negatives, one giant ad group, tracking that optimises for clicks instead of revenue, and PMax cannibalising branded search. Our audit names your exact leaks with screenshots and numbers.'),
        array('q' => 'Do you set up conversion tracking?', 'a' => 'Always, first. Google Tag Manager, enhanced conversions, offline/imported conversions where relevant — because every bidding decision is only as good as the data feeding it.'),
        array('q' => 'How is Performance Max different from normal campaigns?', 'a' => 'PMax lets Google\'s automation buy across all its inventory from one campaign. It\'s powerful and opaque — so we control what we can: asset quality, audience signals, brand exclusions and search-term insights, reviewed weekly.'),
        array('q' => 'What reporting will I see?', 'a' => 'A live dashboard plus a monthly read-out: spend, conversions, CPA/ROAS by campaign, search-term wins and losses, and the exact changes we made because of them.'),
    ),
    'cta' => array(
        'title'  => 'Get a Free Google Ads Audit.',
        'text'   => 'We\'ll review your account structure, search terms and tracking — and show you where the budget is bleeding.',
        'button' => 'Get My Free Audit',
    ),
);

require dirname(__DIR__) . '/includes/service-page.php';

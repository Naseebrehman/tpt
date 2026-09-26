<?php
/**
 * The Pie Technologies — Digital Marketing service page (GROW)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'digital-marketing',
    'title' => 'Digital Marketing',
    'lead'  => 'Every channel pointing at the same outcome — one strategy, one scoreboard.',
    'seoTitle' => 'Digital Marketing Strategy & Multi-Channel Management | TPT',
    'seoDesc'  => 'Integrated campaign strategy across search, social, email and content — audience segmentation, CRM automation, attribution and budget allocation managed as one system.',
    'heroDesc' => 'Running channels in isolation guarantees overlap, gaps and arguments about whose lead it was. We build the integrated system first — offers, audiences, journeys and measurement — then let each channel do the one job it’s best at.',
    'bullets'  => array(
        'One strategy doc covering every active channel',
        'Audience segments shared across campaigns',
        'Journeys automated from first click to repeat purchase',
        'Attribution that settles “which channel worked” with data',
    ),
    'cta' => array(
        'title'  => 'Channels running, but no system connecting them?',
        'text'   => 'Get a marketing systems review: channel map, audience overlap, journey gaps and attribution blind spots — with a one-page plan for how the pieces should connect.',
        'button' => 'Get a Systems Review',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'Five vendors. Zero accountability.',
        'paragraphs' => array(
            'An SEO agency, an ads freelancer, a social intern, an email tool nobody configured and a web designer who left — each reporting their own numbers, none responsible for the only number that matters: revenue from marketing.',
            'The result is predictable: overlapping audiences, contradictory messaging, leads that fall between channels and nobody who can trace a customer back through the journey that produced them.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Every channel reports “success” while revenue stays flat',
            'Leads get captured, then nobody follows up',
            'Messaging differs between ads, site and email',
            'No single view of what marketing produces',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From stranger to repeat customer.',
        'lead'    => 'A governed journey, not a collection of campaigns.',
        'steps'   => array(
            array('title' => 'Attract',  'text' => 'The right channel meets the right person at the right moment — paid for intent, organic for trust.'),
            array('title' => 'Convert',  'text' => 'Every entry point leads to a designed conversion: form, call, purchase — with the offer matched to the audience.'),
            array('title' => 'Capture',  'text' => 'Contacts land in one CRM, segmented by behavior, source and value — never in five disconnected tools.'),
            array('title' => 'Nurture',  'text' => 'Automated journeys move cold leads forward and warm ones to action, in your voice.'),
            array('title' => 'Attribute','text' => 'Every closed customer traced back through the touches that produced it.'),
            array('title' => 'Reallocate','text' => 'Budget and effort flow to the journeys that compound — decided monthly, on evidence.'),
        ),
        'note' => 'Integration is the strategy. Channels are instruments; the system is the orchestra.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The digital marketing operating stack.',
    'pillarsLead'    => 'Four workstreams, run continuously:',
    'pillars' => array(
        array('title' => 'Strategy & channel mix', 'text' => 'The plan that makes channels cooperate.', 'points' => array(
            'Integrated campaign strategy with one offer architecture',
            'Channel roles defined: demand capture vs. demand creation',
            'Budget allocation modeled on cost per outcome per channel',
            'Messaging system consistent from ad to email to invoice',
        )),
        array('title' => 'Audiences & CRM', 'text' => 'Segments that follow people across every touch.', 'points' => array(
            'Audience segmentation shared across paid, organic and email',
            'CRM setup and hygiene: one record per contact, fully tagged',
            'Lead scoring and routing so sales sees the right leads first',
            'Lifecycle automations: welcome, nurture, win-back, upsell',
        )),
        array('title' => 'Campaign orchestration', 'text' => 'Launches run as one motion, not many.', 'points' => array(
            'Cross-channel campaign calendars with shared assets',
            'Retargeting chains across platforms without duplication',
            'Email and SMS journeys triggered by real behavior',
            'Content repurposed deliberately per channel, not reposted blindly',
        )),
        array('title' => 'Attribution & reporting', 'text' => 'One dashboard, one truth.', 'points' => array(
            'Attribution model agreed upfront: first-touch, last-touch or blended',
            'Cross-channel dashboards tied to pipeline, not platform metrics',
            'Monthly reallocation reviews with recommendations, not just data',
            'Quarterly strategy refresh against business goals',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'The first ninety days.',
    'timeline' => array(
        array('when' => 'Week 1–3', 'title' => 'Systems audit',     'text' => 'Every channel, tool and journey mapped. Overlaps, gaps and attribution blind spots documented. Baselines agreed.'),
        array('when' => 'Week 3–4', 'title' => 'Strategy build',    'text' => 'Integrated plan: offer architecture, channel roles, budget model and the one dashboard that will judge it all.'),
        array('when' => 'Month 2',  'title' => 'Foundation',        'text' => 'CRM and tracking unified; segments built; first lifecycle automations live; channel campaigns aligned to the shared calendar.'),
        array('when' => 'Month 3',  'title' => 'Orchestrate',       'text' => 'First cross-channel campaign launched as one motion. Attribution reviewed weekly; budgets shifted on evidence.'),
        array('when' => 'Ongoing',  'title' => 'Compound',          'text' => 'Monthly reallocation reviews and quarterly strategy refreshes keep the system pointed at revenue as the market moves.'),
    ),
    'faqTitle' => 'Digital marketing, straight answers.',
    'faq' => array(
        array('q' => 'How is this different from hiring a specialist per channel?', 'a' => 'Specialists optimize their own channel; we optimize the journey across channels — with one team accountable for the total outcome. When you need deep execution in one channel, our specialists run it inside the same system, so nothing falls between vendors.'),
        array('q' => 'We already run ads and have a website. What would you actually change?', 'a' => 'Usually three things: connect them (shared audiences, consistent offers, real follow-up), measure them (one attribution view instead of five platform dashboards), and compound them (automated journeys that work leads nobody is working). The audit tells you precisely what applies to you.'),
        array('q' => 'Which channels do you cover?', 'a' => 'Search (Google Ads and SEO), social (organic and Meta), email/SMS automation, content, and web — all built or managed in-house, with analytics tying them together. We recommend the mix your market justifies, including channels you should not be on yet.'),
        array('q' => 'Do you work with our existing tools and team?', 'a' => 'Yes. We integrate with your CRM, email platform and analytics stack rather than forcing migrations, and we brief your in-house team so knowledge accumulates on your side too.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint', 'title' => 'The Local Business Marketing Blueprint', 'note' => 'A full marketing system for local businesses, free', 'url' => url('resources/local-business-marketing-blueprint')),
        array('label' => 'Playbook',  'title' => 'The Lead Generation Blueprint',          'note' => 'How the channels connect into one funnel', 'url' => url('resources/lead-generation-blueprint')),
        array('label' => 'Service',   'title' => 'Data Analytics',                         'note' => 'The measurement layer under everything', 'url' => url('services/data-analytics')),
    ),
    'deliverables' => array(
        'Integrated multi-channel strategy and campaign calendar',
        'Offer architecture and consistent messaging system',
        'CRM setup, segmentation and lead scoring',
        'Lifecycle automation: welcome, nurture, win-back journeys',
        'Cross-channel retargeting and audience sharing',
        'Unified tracking and attribution model',
        'One consolidated dashboard with monthly reallocation reviews',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('meta-ads', 'google-ads', 'seo'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

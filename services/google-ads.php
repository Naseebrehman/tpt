<?php
/**
 * The Pie Technologies — Google Ads service page (GROW)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'google-ads',
    'title' => 'Google Ads',
    'lead'  => 'Capture demand that already exists — and pay only for clicks worth having.',
    'seoTitle' => 'Google Ads Management — Search, Performance Max & Local | TPT',
    'seoDesc'  => 'Keyword research, ad copy, landing pages, negative keywords, conversion tracking and budget management across Search, Performance Max, Display and YouTube — governed by cost per qualified result.',
    'heroDesc' => 'Search is intent, captured at the exact moment someone wants what you sell. But intent without structure is expensive: broad match spills, missing negatives, ads that don’t match pages. We run Google Ads like an investment portfolio — every keyword, ad and dollar with a job.',
    'bullets'  => array(
        'Keyword research mapped to buying intent, not volume',
        'Tight ad groups with copy that mirrors the query',
        'Negative keyword lists built before spend starts',
        'Conversion tracking that counts leads, not clicks',
    ),
    'cta' => array(
        'title'  => 'Paying for clicks that never become customers?',
        'text'   => 'Get a Google Ads audit: search terms actually triggering your ads, quality scores, tracking integrity and wasted spend — with the fixes priced against the savings. Often the audit pays for itself.',
        'button' => 'Get a Google Ads Audit',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'Google Ads punishes lazy structure.',
        'paragraphs' => array(
            'Unmanaged accounts drift: broad match sends your ads to searches you’d never bid on, negatives never get added, and every click teaches the system to find more of the same mistake. The budget disappears into queries one honest search-terms report would have exposed.',
            'Meanwhile your landing pages promise something different than the ad, quality scores sag, and Google charges you a tax for the mismatch — the least efficient media buy in digital, wearing the costume of the most efficient one.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Search terms report full of queries that aren’t your customers',
            'Cost per click rising while conversion rate falls',
            '“Just set it to Performance Max and let Google handle it”',
            'No one can say what a lead from Google actually costs',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From query to customer.',
        'lead'    => 'Six governed stages between someone typing and someone buying.',
        'steps'   => array(
            array('title' => 'Query',     'text' => 'Someone searches with intent. Keyword architecture decides whether your ad earns the right to answer.'),
            array('title' => 'Ad',        'text' => 'Copy that mirrors the query’s language, with every extension loaded — sitelinks, callouts, structured snippets.'),
            array('title' => 'Page',      'text' => 'A landing page that continues the conversation, answers the objection and makes one ask.'),
            array('title' => 'Convert',   'text' => 'Tracked conversion — form, call, purchase — with server-side verification so the data is real.'),
            array('title' => 'Govern',    'text' => 'Search terms reviewed weekly: negatives added, matches tightened, budget moved toward what converts.'),
            array('title' => 'Compound',  'text' => 'Clean conversion data trains Smart Bidding properly; quality scores rise; cost per result falls.'),
        ),
        'note' => 'Every week we ask one question of every keyword: did it produce a customer, a reason to say no, or noise?',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The Google Ads operating stack.',
    'pillarsLead'    => 'Four workstreams, run continuously:',
    'pillars' => array(
        array('title' => 'Research & structure', 'text' => 'Intent-mapped keywords in tight, sane ad groups.', 'points' => array(
            'Keyword research organized by buying intent, not search volume',
            'Tight ad groups so every ad answers its own query',
            'Match types used deliberately — broad only where negatives guard it',
            'Campaign budgets split across search, local and remarketing by role',
        )),
        array('title' => 'Ads & landing pages', 'text' => 'Message match from query to form.', 'points' => array(
            'RSA copy tested against real queries; extensions fully loaded',
            'Landing pages built per campaign, continuing the ad’s promise',
            'Forms and call tracking that capture and qualify the lead',
            'Page speed and mobile experience treated as bid variables',
        )),
        array('title' => 'Negatives & quality', 'text' => 'The work that makes everything else cheaper.', 'points' => array(
            'Weekly search-terms reviews; negative lists grown continuously',
            'Shared negative libraries across campaigns',
            'Quality score improvement via relevance, not bid brute-force',
            'Geo, device and daypart bid adjustments from real conversion data',
        )),
        array('title' => 'Tracking & bidding', 'text' => 'Clean signals feeding disciplined automation.', 'points' => array(
            'GA4 + Google Ads conversion linking with enhanced conversions',
            'Call tracking and offline conversion import where sales close offline',
            'Smart Bidding introduced only after conversion volume justifies it',
            'Budget scaling rules tied to cost per qualified result',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'The first ninety days.',
    'timeline' => array(
        array('when' => 'Week 1–2', 'title' => 'Audit & research',      'text' => 'Account and search-terms teardown; keyword universe mapped by intent; competitor auction insights reviewed; baselines recorded.'),
        array('when' => 'Week 2–3', 'title' => 'Structure & tracking',  'text' => 'Campaign architecture rebuilt; GA4 and conversion tracking verified; enhanced conversions and call tracking wired.'),
        array('when' => 'Week 3–4', 'title' => 'Ads & pages',           'text' => 'RSAs written per ad group; extensions loaded; landing pages built or fixed for message match.'),
        array('when' => 'Week 5–6', 'title' => 'Launch & govern',       'text' => 'Staged launch with budgets guarded; first negative lists seeded; daily delivery and weekly search-terms reviews begin.'),
        array('when' => 'Week 7–10','title' => 'Optimize',              'text' => 'Bids and budgets move toward converting terms; quality scores lifted; ad copy iterated against real query data.'),
        array('when' => 'Week 11–13','title' => 'Scale',                'text' => 'New keyword themes, remarketing and Performance Max added where evidence supports them. Quarterly plan agreed.'),
    ),
    'faqTitle' => 'Google Ads, straight answers.',
    'faq' => array(
        array('q' => 'Google Ads or Meta Ads — which should we run?', 'a' => 'They answer different moments: Google captures existing demand, Meta creates it. If people already search for what you sell, Google usually earns budget first. Many businesses run both deliberately — we model the blend from your market’s search volume and costs, then recommend honestly.'),
        array('q' => 'What does a click cost in our industry?', 'a' => 'It varies by market, keyword and quality score — a home-services click and a legal click are different universes. During discovery we pull auction and keyword data for your specific geography and model cost per lead before you commit a dollar.'),
        array('q' => 'Can you take over an existing account?', 'a' => 'Yes, and we often do. We audit before touching anything: what’s working gets kept, what’s leaking gets fixed, and the account history — quality scores, conversion data — is preserved rather than restarted from zero.'),
        array('q' => 'Do we need a landing page, or can ads go to our website?', 'a' => 'You can, and you’ll pay more for it. A homepage answers everyone; a landing page answers the person who clicked this ad. We build message-matched pages per campaign and measure the difference in conversion rate, not opinion.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint', 'title' => 'The Google Ads Blueprint',        'note' => 'Structure, negatives and bidding, free', 'url' => url('resources/google-ads-blueprint')),
        array('label' => 'Playbook',  'title' => 'The Lead Generation Blueprint',   'note' => 'Full-funnel context for paid search', 'url' => url('resources/lead-generation-blueprint')),
        array('label' => 'Service',   'title' => 'Web Development',                 'note' => 'Landing pages built to convert', 'url' => url('services/web-development')),
    ),
    'deliverables' => array(
        'Keyword research mapped to buying intent',
        'Campaign and ad-group architecture with deliberate match types',
        'Responsive search ads and every applicable extension',
        'Negative keyword libraries grown from weekly search-terms reviews',
        'Landing pages with message match per campaign',
        'GA4 conversion tracking, enhanced conversions and call tracking',
        'Bid and budget management with monthly performance reporting',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('seo', 'web-development', 'data-analytics'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

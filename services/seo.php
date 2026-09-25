<?php
/**
 * The Pie Technologies — SEO service page
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'seo',
    'title' => 'Rank Higher. Get Found. Grow Faster.',
    'lead'  => 'Technical fixes, on-page optimisation, authority building and content that answers real search intent. Compounding traffic you own outright — no ad budget required.',
    'seoTitle' => 'SEO Agency | Rank Higher on Google and Keep the Traffic',
    'seoDesc'  => 'On-page, off-page, technical, local, e-commerce and content SEO. Transparent process, real tools (Ahrefs, SEMrush, GSC) and honest timelines from month 1 to month 12.',
    'intro'   => array(
        'heading'    => 'Service breakdown',
        'title'      => 'SEO that compounds, not tricks that expire.',
        'paragraphs' => array(
            'Google rewards sites that are fast, clear, trustworthy and genuinely useful. Our job is to make yours all four — then earn the links and mentions that prove it to the algorithm.',
            'No private-blog-network spam, no keyword stuffing, no "2,000 backlinks for $50". Just the disciplined, unglamorous work that keeps paying for years after the invoices stop.',
        ),
        'features' => array(
            array('icon' => 'edit',    'title' => 'On-Page SEO',        'text' => 'Titles, meta descriptions, headers, internal links and content depth optimised page by page against search intent.'),
            array('icon' => 'globe',   'title' => 'Off-Page SEO',       'text' => 'Digital PR, guest placements and authority links from real sites with real traffic — never link farms.'),
            array('icon' => 'cpu',     'title' => 'Technical SEO',      'text' => 'Crawlability, indexation, Core Web Vitals, schema markup and site architecture fixed at the root.'),
            array('icon' => 'pin',     'title' => 'Local SEO',          'text' => 'Google Business Profile optimisation, citations and review velocity so you own the map pack in your city.'),
            array('icon' => 'grid',    'title' => 'E-commerce SEO',     'text' => 'Category architecture, product schema, faceted-navigation control and feed hygiene for online stores.'),
            array('icon' => 'pen',     'title' => 'Content SEO',        'text' => 'Topic clusters and articles built around questions your buyers actually type into Google.'),
        ),
    ),
    'steps' => array(
        array('title' => 'Audit',        'text' => 'Full technical, content and backlink audit with a prioritised fix list.'),
        array('title' => 'Keywords',     'text' => 'Intent-mapped keyword strategy: money pages first, supporting clusters second.'),
        array('title' => 'On-Page',      'text' => 'Page-by-page optimisation, internal linking and content upgrades shipped.'),
        array('title' => 'Link Building','text' => 'Authority campaigns: digital PR, guest posts and unlinked-mention recovery.'),
        array('title' => 'Reporting',    'text' => 'Monthly ranking, traffic and conversion reporting against agreed KPIs.'),
    ),
    'tools' => array('Ahrefs', 'SEMrush', 'Google Search Console', 'Screaming Frog', 'Moz'),
    'timeline' => array(
        array('when' => 'Month 1–3', 'title' => 'Foundations & early movement', 'text' => 'Technical fixes land, indexation cleans up, and low-competition keywords start moving. Long-tail traffic typically rises first while authority builds quietly in the background.'),
        array('when' => 'Month 3–6', 'title' => 'Momentum on money keywords',   'text' => 'Optimised pages climb through page two and into the top ten. Content clusters begin ranking as a group, and organic leads become visible in your analytics — not just your reports.'),
        array('when' => 'Month 6–12','title' => 'Compounding & category ownership', 'text' => 'Authority links mature, competitive head terms enter the top three, and organic becomes a predictable acquisition channel you stop paying rent on.'),
    ),
    'chartTitle' => 'Typical organic sessions curve (client average, first 12 months)',
    'chart' => array(
        'type' => 'line',
        'data' => array(
            'labels' => array('M1', 'M2', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8', 'M9', 'M10', 'M11', 'M12'),
            'datasets' => array(
                array(
                    'label' => 'Organic sessions',
                    'data'  => array(420, 455, 510, 610, 760, 940, 1180, 1470, 1820, 2240, 2760, 3380),
                ),
            ),
        ),
    ),
    'stats' => array(
        array('value' => 212, 'suffix' => '%', 'label' => 'Average organic traffic growth in 12 months'),
        array('value' => 3,   'suffix' => '',  'label' => 'Average position for money keywords (from #31)'),
        array('value' => 12,  'suffix' => ' mo', 'label' => 'To a compounding channel you own outright'),
    ),
    'testimonial' => true,
    'faq' => array(
        array('q' => 'How long does SEO take to work?', 'a' => 'Honest answer: meaningful movement in 3–6 months, compounding results in 6–12. Anyone promising page-one rankings in 30 days is selling something Google will eventually punish. We set milestones at months 3, 6 and 12 so you can judge progress against a real curve.'),
        array('q' => 'What\'s the difference between technical and content SEO?', 'a' => 'Technical SEO makes your site crawlable, fast and understandable to Google. Content SEO gives Google a reason to rank you. You need both — a perfect site with nothing worth reading ranks nothing, and great content on a broken site never gets crawled properly.'),
        array('q' => 'Do you guarantee #1 rankings?', 'a' => 'No ethical SEO can guarantee a position — Google\'s algorithm is not for sale. We guarantee process: a prioritised roadmap, shipped fixes, quality links and transparent monthly reporting of rankings, traffic and conversions.'),
        array('q' => 'Local or national — which should I do?', 'a' => 'If your revenue comes from a city or region, local SEO (map pack + localised landing pages) delivers fastest ROI. If you sell everywhere, we build national/international clusters. Many clients run both: local for cash flow, national for scale.'),
        array('q' => 'We already ran SEO once and it did nothing. Why?', 'a' => 'Usually one of three causes: thin content targeting keywords with no intent, links from spam networks that got discounted, or technical debt that blocked indexation. Our audit will tell you exactly which one happened to you — and whether the old work is salvageable.'),
        array('q' => 'What do you need from us?', 'a' => 'Access (Search Console, analytics, CMS), a monthly check-in, and subject-matter expertise when we need fact-checks. Everything else — strategy, writing, development fixes, links — is on us.'),
    ),
    'cta' => array(
        'title'  => 'Get a Free SEO Audit.',
        'text'   => 'A 20-point teardown of your site: what\'s blocking you, what\'s working, and the three moves with the fastest payoff.',
        'button' => 'Get My Free Audit',
    ),
);

require dirname(__DIR__) . '/includes/service-page.php';

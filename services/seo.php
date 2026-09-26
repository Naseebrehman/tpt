<?php
/**
 * The Pie Technologies — SEO service page (GET FOUND)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'seo',
    'title' => 'Search Engine Optimization',
    'lead'  => 'Own the searches that make your business — rankings that pay every month.',
    'seoTitle' => 'SEO Services — Technical, On-Page, Content & Authority | TPT',
    'seoDesc'  => 'Technical SEO, on-page optimization, content strategy, link building and schema for businesses that want durable organic visibility — with reporting that ties rankings to revenue.',
    'heroDesc' => 'Paid traffic stops when the budget stops. Organic traffic compounds: every ranking you earn keeps producing for years. We build that asset deliberately — technical foundation, pages that answer real queries, and authority earned, never bought from link farms.',
    'bullets'  => array(
        'Technical audit with fixes shipped, not just listed',
        'Content mapped to queries your buyers actually type',
        'Links earned through real digital PR and partnerships',
        'Rankings tied to leads and revenue in reporting',
    ),
    'cta' => array(
        'title'  => 'Ranking for everything except what sells?',
        'text'   => 'Get an SEO audit: technical health, content gaps, authority profile and competitor comparison — with a prioritized roadmap showing what will move revenue first.',
        'button' => 'Get a Free SEO Audit',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'Traffic is not the goal. Customers are.',
        'paragraphs' => array(
            'Most SEO programs chase volume: rankings for terms nobody buys on, reports full of impressions, and a gap between “we’re number one” and “the phone rang.” Meanwhile the technical foundation leaks — slow pages, thin content, no schema — and authority stalls.',
            'Done properly, SEO is a business channel with a lag: boring foundations first, then pages that answer buying queries, then authority that makes those pages impossible to dislodge. It rewards patience and punishes shortcuts — and it keeps paying after the invoice is settled.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Rankings improved, revenue didn’t',
            'An audit delivered as a 90-page PDF nobody implemented',
            'Blog posts published on topics no customer searches',
            'Backlinks bought cheap that now need disavowing',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From query to customer.',
        'lead'    => 'Six governed stages of compounding visibility.',
        'steps'   => array(
            array('title' => 'Crawl',    'text' => 'Google finds every page that matters and nothing that wastes its budget. Technical health shipped, not just reported.'),
            array('title' => 'Understand','text' => 'Schema, structure and content clarity tell the engine exactly what you offer, where and for whom.'),
            array('title' => 'Rank',     'text' => 'Relevance from on-page work meets authority from earned links — the two levers, pulled in order.'),
            array('title' => 'Click',    'text' => 'Titles, metas and rich results written to earn the click against the nine blue competitors.'),
            array('title' => 'Convert',  'text' => 'The landing experience answers the query and asks for one thing — tracked back to the keyword.'),
            array('title' => 'Compound', 'text' => 'Performance data funds the next content and link decisions. The asset grows monthly.'),
        ),
        'note' => 'We report on rankings tied to pipeline: which queries produced leads, calls and revenue — not screenshots of position graphs.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The SEO operating stack.',
    'pillarsLead'    => 'Four workstreams, run continuously:',
    'pillars' => array(
        array('title' => 'Technical foundation', 'text' => 'Boring, decisive, shipped.', 'points' => array(
            'Crawl, indexation and site-architecture fixes implemented',
            'Core Web Vitals improvements with developers, not wish lists',
            'Schema markup: organization, service, FAQ, article, local',
            'Redirect hygiene, canonicals and duplicate resolution',
        )),
        array('title' => 'On-page & content', 'text' => 'Pages built for queries buyers type.', 'points' => array(
            'Keyword and topic research mapped to commercial intent',
            'Page-level optimization: titles, structure, internal links',
            'Content strategy that answers questions across the buying cycle',
            'Existing pages refreshed instead of abandoned',
        )),
        array('title' => 'Authority & links', 'text' => 'Earned, not manufactured.', 'points' => array(
            'Digital PR: data, guides and stories worth linking to',
            'Partnership and supplier links from real relationships',
            'Toxic link cleanup and disavow where needed',
            'Authority tracked at topic level, not just domain score',
        )),
        array('title' => 'Measurement & reporting', 'text' => 'Visibility tied to business outcomes.', 'points' => array(
            'Rank tracking per money query and per location',
            'GA4 + Search Console integrated with CRM where possible',
            'Monthly report: rankings, leads, revenue and next actions',
            'Competitor movement monitored and answered',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'The first six months.',
    'timeline' => array(
        array('when' => 'Month 1',   'title' => 'Audit & foundation', 'text' => 'Full technical, content and authority audit; prioritized roadmap agreed; critical fixes shipped immediately.'),
        array('when' => 'Month 1–2', 'title' => 'Architecture',       'text' => 'Site structure, internal linking and schema deployed; Core Web Vitals work begins with your developers or ours.'),
        array('when' => 'Month 2–3', 'title' => 'Money pages',        'text' => 'Highest-intent pages optimized or built first — the queries closest to revenue.'),
        array('when' => 'Month 3–4', 'title' => 'Content engine',     'text' => 'Editorial calendar executing: buying-cycle content published and internally linked to money pages.'),
        array('when' => 'Month 4–6', 'title' => 'Authority',          'text' => 'Digital PR and link campaigns running; toxic links cleaned; topic-level authority building.'),
        array('when' => 'Ongoing',   'title' => 'Compound',           'text' => 'Monthly reporting against pipeline; quarterly roadmaps refreshed as rankings, competitors and Google itself move.'),
    ),
    'faqTitle' => 'SEO, straight answers.',
    'faq' => array(
        array('q' => 'How long until we see results?', 'a' => 'Technical fixes and money-page optimizations often move rankings within 6–12 weeks; meaningful organic pipeline is typically a 4–6 month arc, and authority-driven dominance takes longer. Anyone promising page-one in 30 days is selling you a penalty risk. We show leading indicators — crawl health, ranking movement, content coverage — from month one.'),
        array('q' => 'SEO or Google Ads?', 'a' => 'Ads rent visibility today; SEO buys the asset that pays for years. If you need leads this month and have budget, run both: ads capture demand while SEO compounds. If budget is tight and your timeline allows, SEO first builds the cheaper long-run channel. We model both honestly for your market.'),
        array('q' => 'Do you guarantee rankings?', 'a' => 'No — and neither should anyone. Google’s algorithms and your competitors aren’t ours to promise. We guarantee the work: audits shipped, content published, links earned, and reporting that ties visibility to revenue. If the numbers don’t move, we keep working until they do or tell you plainly why they won’t.'),
        array('q' => 'What about AI search — does SEO still matter?', 'a' => 'It matters more, differently. AI engines cite sources they trust: structured, schema-marked, authoritative pages. Our approach — clean technicals, entity clarity, content that answers questions completely — is built to be cited by Google and AI assistants alike. See our AI Business Optimization service for the dedicated play.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint', 'title' => 'The SEO Blueprint',                  'note' => 'The full organic playbook, free', 'url' => url('resources/seo-blueprint')),
        array('label' => 'Blueprint', 'title' => 'AI Search Visibility Blueprint',     'note' => 'How to get cited by AI engines', 'url' => url('resources/ai-search-visibility-blueprint')),
        array('label' => 'Service',   'title' => 'Local SEO',                          'note' => 'The map-pack play for local businesses', 'url' => url('services/local-seo')),
    ),
    'deliverables' => array(
        'Technical SEO audit with implemented fixes',
        'Keyword and topic research mapped to buying intent',
        'On-page optimization of money pages and content refreshes',
        'Content strategy and editorial calendar execution',
        'Schema markup across organization, service, FAQ and local types',
        'Digital PR and ethical link building',
        'Rank tracking tied to leads and revenue, reported monthly',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('local-seo', 'ai-business-optimization', 'web-development'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

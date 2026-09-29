<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — shared site data (disciplines, services, nav)
 * ---------------------------------------------------------------------------
 *  Static presentation data used by the navbar, home page, service pages and
 *  footer. Database-driven content (blog, portfolio/work, testimonials, team,
 *  resources) lives in MySQL instead.
 *
 *  Structure: 5 disciplines → 11 services (one accountable team).
 * ---------------------------------------------------------------------------
 */

/** The five growth disciplines, in brand order. */
function pieDisciplines()
{
    return array(
        array('key' => 'grow',      'num' => '01', 'name' => 'GROW',      'label' => 'Demand & performance marketing', 'desc' => 'Paid media and campaigns that put your offer in front of buyers — and turn budget into pipeline.'),
        array('key' => 'get-found', 'num' => '02', 'name' => 'GET FOUND', 'label' => 'Search & local visibility',      'desc' => 'Own the moments people search. Organic, local and AI-search visibility that compounds over time.'),
        array('key' => 'build',     'num' => '03', 'name' => 'BUILD',     'label' => 'Websites, apps & platforms',     'desc' => 'Fast, conversion-built websites and custom software that act as the engine of your growth.'),
        array('key' => 'create',    'num' => '04', 'name' => 'CREATE',    'label' => 'Creative & content',             'desc' => 'Brand-level creative — ads, graphics and content systems built to stop the scroll and sell.'),
        array('key' => 'measure',   'num' => '05', 'name' => 'MEASURE',   'label' => 'Data & optimization',            'desc' => 'Analytics, reporting and conversion optimization so every decision is made on evidence.'),
    );
}

/** The eleven services, grouped by discipline. */
function pieServices()
{
    return array(
        /* ------------------------------- GROW ------------------------------ */
        array(
            'key'        => 'meta-ads',
            'discipline' => 'grow',
            'num'        => '01',
            'name'       => 'Meta Ads',
            'core'       => true,
            'tagline'    => 'Facebook & Instagram campaigns engineered to turn attention into pipeline.',
            'desc'       => 'Full-funnel paid social on Facebook & Instagram: campaign architecture, audience strategy, creative testing, retargeting and conversion tracking — optimized against cost per result, not vanity metrics.',
            'tags'       => array('Lead Generation', 'Creative Testing', 'Retargeting', 'CAPI Tracking'),
            'icon'       => 'target',
            'image'      => 'assets/images/work-fashion.jpg',
        ),
        array(
            'key'        => 'social-media-management',
            'discipline' => 'grow',
            'num'        => '02',
            'name'       => 'Social Media Management',
            'core'       => true,
            'tagline'    => 'A content engine that keeps your brand in the feed — and in the shortlist.',
            'desc'       => 'Pillar-based content strategy, production, scheduling and community management across the platforms your buyers actually use — run on a weekly rhythm with measurement built in.',
            'tags'       => array('Content Pillars', 'Short-Form Video', 'Community', 'Calendars'),
            'icon'       => 'megaphone',
            'image'      => 'assets/images/blog-social.jpg',
        ),
        array(
            'key'        => 'google-ads',
            'discipline' => 'grow',
            'num'        => '03',
            'name'       => 'Google Ads',
            'core'       => true,
            'tagline'    => 'Capture buyers at the exact moment they’re searching.',
            'desc'       => 'Search, Performance Max, Shopping and YouTube campaigns built on intent tiers, negative-keyword discipline and real conversion tracking — with a weekly optimization rhythm that keeps spend efficient.',
            'tags'       => array('Search', 'Performance Max', 'Negative Keywords', 'Call Tracking'),
            'icon'       => 'chart',
            'image'      => 'assets/images/work-cafe.jpg',
        ),
        array(
            'key'        => 'digital-marketing',
            'discipline' => 'grow',
            'num'        => '04',
            'name'       => 'Digital Marketing — The Full System',
            'core'       => false,
            'tagline'    => 'One team. Every channel. Zero gaps between them.',
            'desc'       => 'The complete TPT engagement: strategy, paid media, search, content, website and data run as one growth machine — with a single strategist accountable for the whole board.',
            'tags'       => array('Multi-Channel', 'CRM Journeys', 'Attribution', 'Budget Modeling'),
            'icon'       => 'layers',
            'image'      => 'assets/images/hero-studio.jpg',
        ),
        /* ---------------------------- GET FOUND ---------------------------- */
        array(
            'key'        => 'seo',
            'discipline' => 'get-found',
            'num'        => '05',
            'name'       => 'SEO',
            'core'       => true,
            'tagline'    => 'Search rankings built on engineering, not rituals.',
            'desc'       => 'Technical SEO, intent-mapped content architecture, internal linking and authority that lasts — measured in qualified organic pipeline, not ranking screenshots.',
            'tags'       => array('Technical SEO', 'Content', 'Link Building', 'Schema'),
            'icon'       => 'search',
            'image'      => 'assets/images/why-data.jpg',
        ),
        array(
            'key'        => 'local-seo',
            'discipline' => 'get-found',
            'num'        => '06',
            'name'       => 'Local SEO',
            'core'       => false,
            'tagline'    => 'Own the map pack in the areas that pay you.',
            'desc'       => 'Google Business Profile, reviews, citations, location pages and local schema — systemized so “near me” searches turn into calls from the neighborhoods you actually serve.',
            'tags'       => array('Business Profile', 'Reviews', 'Citations', 'Map Pack'),
            'icon'       => 'pin',
            'image'      => 'assets/images/why-team.jpg',
        ),
        array(
            'key'        => 'ai-business-optimization',
            'discipline' => 'get-found',
            'num'        => '07',
            'name'       => 'AI Business Optimization',
            'core'       => true,
            'tagline'    => 'Be the answer AI engines can actually read — and safely recommend.',
            'desc'       => 'Make your business legible to ChatGPT, Gemini, Perplexity and AI Overviews: entity clarity, structured data and answer-shaped content — with honest scope and real checks.',
            'tags'       => array('AI Visibility', 'Automation', 'Custom Agents', 'Structured Data'),
            'icon'       => 'cpu',
            'image'      => 'assets/images/work-saas.jpg',
        ),
        /* ------------------------------ BUILD ------------------------------ */
        array(
            'key'        => 'web-development',
            'discipline' => 'build',
            'num'        => '08',
            'name'       => 'Website Development',
            'core'       => true,
            'tagline'    => 'Your website should work harder than a brochure.',
            'desc'       => 'Conversion-focused websites engineered for speed, mobile, SEO architecture and measurement — landing pages, business sites, e-commerce and custom functionality that carries the sale.',
            'tags'       => array('Custom Builds', 'eCommerce', 'Core Web Vitals', 'CMS'),
            'icon'       => 'code',
            'image'      => 'assets/images/work-saas.jpg',
        ),
        array(
            'key'        => 'app-development',
            'discipline' => 'build',
            'num'        => '09',
            'name'       => 'App Development',
            'core'       => false,
            'tagline'    => 'Software that removes the bottlenecks your spreadsheets are hiding.',
            'desc'       => 'Custom web apps, portals, automations and integrations built on boring, proven stacks — scoped to the whole problem, instrumented end to end, and owned by you.',
            'tags'       => array('iOS & Android', 'Cross-Platform', 'APIs', 'Store Launch'),
            'icon'       => 'grid',
            'image'      => 'assets/images/hero-studio.jpg',
        ),
        /* ------------------------------ CREATE ----------------------------- */
        array(
            'key'        => 'graphic-design',
            'discipline' => 'create',
            'num'        => '10',
            'name'       => 'Graphic Design',
            'core'       => false,
            'tagline'    => 'Creative that stops the scroll — and carries the sale.',
            'desc'       => 'Ad creative, brand systems and content design built as a testing engine: hooks, formats and angles produced in volume, judged on evidence, scaled when they win.',
            'tags'       => array('Brand Identity', 'Ad Creative', 'Templates', 'Guidelines'),
            'icon'       => 'pen',
            'image'      => 'assets/images/work-fitness.jpg',
        ),
        /* ----------------------------- MEASURE ----------------------------- */
        array(
            'key'        => 'data-analytics',
            'discipline' => 'measure',
            'num'        => '11',
            'name'       => 'Data Analytics & Reporting',
            'core'       => false,
            'tagline'    => 'Dashboards that end arguments and start decisions.',
            'desc'       => 'Tracking architecture, server-side events, dashboards and monthly reporting that speaks in leads, quality, cost and revenue — the evidence layer under every channel.',
            'tags'       => array('GA4', 'Dashboards', 'Attribution', 'A/B Testing'),
            'icon'       => 'eye',
            'image'      => 'assets/images/why-data.jpg',
        ),
    );
}

function pieServiceByKey($key)
{
    foreach (pieServices() as $service) {
        if ($service['key'] === $key) {
            return $service;
        }
    }
    return null;
}

function pieServicesByDiscipline($disciplineKey)
{
    $out = array();
    foreach (pieServices() as $service) {
        if ($service['discipline'] === $disciplineKey) {
            $out[] = $service;
        }
    }
    return $out;
}

/** The six flagship services shown in "Where growth usually starts." */
function pieCoreServices()
{
    $out = array();
    foreach (pieServices() as $service) {
        if (!empty($service['core'])) {
            $out[] = $service;
        }
    }
    return $out;
}

/** Redirects for retired service slugs — old URLs keep working. */
function pieServiceRedirects()
{
    return array(
        'email-marketing' => 'digital-marketing',
        'branding-design' => 'graphic-design',
    );
}

/** Main navigation structure (grouped services dropdown). */
function pieNav()
{
    return array(
        array('key' => 'home',      'label' => 'Home',           'url' => url('')),
        array('key' => 'services',  'label' => 'Services',       'url' => url('services'), 'dropdown' => true),
        array('key' => 'portfolio', 'label' => 'Work',           'url' => url('portfolio')),
        array('key' => 'resources', 'label' => 'Growth Library', 'url' => url('resources')),
        array('key' => 'blog',      'label' => 'Journal',        'url' => url('blog')),
        array('key' => 'about',     'label' => 'About',          'url' => url('about')),
        array('key' => 'pay',       'label' => 'Pay Online',     'url' => url('pay-online')),
        array('key' => 'contact',   'label' => 'Contact',        'url' => url('contact')),
    );
}

/** Industries TPT builds for (home page interactive section). */
function pieIndustries()
{
    return array(
        array('abbr' => 'RF', 'name' => 'Roofing',              'copy' => 'Storm-season demand capture: map visibility, insurance-proof content and Meta ads targeted by home age — plus instant lead routing while calls are hot.', 'services' => array('local-seo', 'meta-ads', 'web-development')),
        array('abbr' => 'SO', 'name' => 'Solar',                'copy' => 'High-ticket, high-competition leads: appointment-setter funnels, financing-message creative and cost-per-sit-Down tracked from click to contract.', 'services' => array('meta-ads', 'google-ads', 'data-analytics')),
        array('abbr' => 'LS', 'name' => 'Landscaping',          'copy' => 'Season-peeked demand smoothed with recurring-revenue offers, route-density-aware local pages and creative that sells the transformation, not the mow.', 'services' => array('local-seo', 'social-media-management', 'graphic-design')),
        array('abbr' => 'CN', 'name' => 'Construction',         'copy' => 'Long sales cycles, big contract values: credibility-first websites, project-gallery content engines and search coverage for every service line and city.', 'services' => array('web-development', 'seo', 'graphic-design')),
        array('abbr' => 'HS', 'name' => 'Home Services',        'copy' => 'The phone is the funnel: call tracking, review velocity and service-area pages that keep crews booked — speed-to-lead engineered into the process.', 'services' => array('local-seo', 'google-ads', 'data-analytics')),
        array('abbr' => 'PS', 'name' => 'Professional Services','copy' => 'Trust is the product: authority content, retargeting that stays respectful, and lead qualification that protects partner time from tire-kickers.', 'services' => array('seo', 'meta-ads', 'web-development')),
        array('abbr' => 'HC', 'name' => 'Healthcare',           'copy' => 'Compliance-aware growth: HIPAA-conscious tracking, condition-specific content architecture and patient-journey funnels from first search to booked visit.', 'services' => array('seo', 'local-seo', 'web-development')),
        array('abbr' => 'TC', 'name' => 'Technology',           'copy' => 'Selling to people who block ads: product-led landing pages, comparison and integration content, and AI-search visibility for the queries engineers actually run.', 'services' => array('web-development', 'ai-business-optimization', 'data-analytics')),
        array('abbr' => 'RE', 'name' => 'Real Estate',          'copy' => 'Listings are commodities; agents are brands: hyper-local pages, listing syndication hygiene and nurture sequences that work the pipeline between deals.', 'services' => array('local-seo', 'social-media-management', 'meta-ads')),
        array('abbr' => 'EC', 'name' => 'E-commerce',           'copy' => 'Margin-aware media: catalogue campaigns, creative testing against purchase ROAS, retention flows and the on-site conversion work that makes traffic pay.', 'services' => array('meta-ads', 'google-ads', 'web-development')),
        array('abbr' => 'LB', 'name' => 'Local Businesses',     'copy' => 'The complete local system in one engagement: be found on the map, be believed in reviews, be easy to book — and be measurable about all three.', 'services' => array('local-seo', 'google-ads', 'social-media-management')),
    );
}

/** Home page FAQ (straight answers). */
function pieHomeFaq()
{
    return array(
        array('q' => 'How is TPT different from a typical marketing agency?', 'a' => 'Most agencies sell isolated services — a few ads here, a post there. TPT builds connected growth systems: strategy, creative, media, websites and data working as one engine, with a single accountable team.'),
        array('q' => 'Do you work with businesses outside the US?', 'a' => 'Yes. With locations in Collingswood, NJ and Punjab, Pakistan, we serve clients across time zones. Strategy calls, reporting and delivery are all built for remote collaboration.'),
        array('q' => 'How much do your services cost?', 'a' => 'Every engagement is scoped to your goals, market and stage of growth — so we quote per project or retainer, not from a generic price list. Tell us your goal and we will come back with a concrete plan and honest numbers.'),
        array('q' => 'How quickly will we see results?', 'a' => 'It depends on the channel. Paid campaigns can generate leads within days of launch; SEO and AI-search visibility compound over months. We set expectations in writing before anything starts — no vague promises.'),
        array('q' => 'Who owns the ad accounts, website and data?', 'a' => 'You do. Always. Campaigns, pixels, analytics, creative files and code live in accounts you own, with TPT added as a partner. If we ever part ways, everything stays with you.'),
        array('q' => 'What does working together look like?', 'a' => 'Discover → Strategy → Build → Launch → Optimize → Scale. You get one point of contact, a shared project board, weekly updates and a monthly performance review with real numbers.'),
    );
}

/** Shared social profiles for contact and footer; settings override project defaults. */
function pieSocialLinks()
{
    $definitions = array(
        array('instagram_url', 'instagram', 'Instagram', 'https://instagram.com/thepietechnologies'),
        array('facebook_url', 'facebook', 'Facebook', 'https://facebook.com/thepietechnologies'),
        array('linkedin_url', 'linkedin', 'LinkedIn', 'https://linkedin.com/company/thepietechnologies'),
        array('tiktok_url', 'tiktok', 'TikTok', ''),
        array('twitter_url', 'twitter', 'X / Twitter', ''),
        array('youtube_url', 'youtube', 'YouTube', ''),
    );
    $settings = settingsCache();
    $links = array();
    foreach ($definitions as $definition) {
        $href = trim((string) (array_key_exists($definition[0], $settings) ? $settings[$definition[0]] : $definition[3]));
        if ($href !== '' && filter_var($href, FILTER_VALIDATE_URL) && in_array(parse_url($href, PHP_URL_SCHEME), array('http', 'https'), true)) {
            $links[] = array('icon'=>$definition[1], 'label'=>$definition[2], 'url'=>$href);
        }
    }
    return $links;
}

/** Inline SVG icon library (stroke style, inherits currentColor). */
function icon($name, $size = 20)
{
    $paths = array(
        'target'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',
        'megaphone' => '<path d="M3 11v3l14 5V6L3 11z"/><path d="M17 8a4 4 0 0 1 0 8"/><path d="M7 15v4a1 1 0 0 0 1 1h2"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'code'      => '<path d="m8 7-5 5 5 5"/><path d="m16 7 5 5-5 5"/><path d="m13 4-2 16"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'chart'     => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'pen'       => '<path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/>',
        'rocket'    => '<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2c.8-.8.8-2.2 0-3-.8-.8-2.2-.8-3 0z"/><path d="M12 15l-3-3c1-4 4-8 10-9-1 6-5 9-9 10z"/><path d="M9 12H5l2-4h4"/><path d="M12 15v4l4-2v-4"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-4 3.2-6 6.5-6s6 2 6.5 6"/><circle cx="17.5" cy="9" r="2.8"/><path d="M16 14.2c2.9.2 5 2 5.5 5.3"/>',
        'zap'       => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/>',
        'shield'    => '<path d="M12 2 4 6v6c0 5 3.5 8.5 8 10 4.5-1.5 8-5 8-10V6l-8-4z"/><path d="m9 12 2 2 4-4"/>',
        'check'     => '<path d="m4 12.5 5 5L20 6.5"/>',
        'arrow-r'   => '<path d="M4 12h16"/><path d="m14 6 6 6-6 6"/>',
        'arrow-d'   => '<path d="M12 4v16"/><path d="m6 14 6 6 6-6"/>',
        'arrow-l'   => '<path d="M20 12H4"/><path d="m10 6-6 6 6 6"/>',
        'arrow-u'   => '<path d="M12 20V4"/><path d="m6 10 6-6 6 6"/>',
        'plus'      => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'close'     => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
        'menu'      => '<path d="M3 7h18"/><path d="M3 12h18"/><path d="M3 17h18"/>',
        'phone'     => '<path d="M5 3h4l2 5-2.5 1.5a12 12 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'pin'       => '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'star'      => '<path d="m12 3 2.7 5.7 6.3.8-4.6 4.3 1.2 6.2-5.6-3.1-5.6 3.1 1.2-6.2L3 9.5l6.3-.8L12 3z"/>',
        'download'  => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 19h16"/>',
        'play'      => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 6 3.5-6 3.5v-7z"/>',
        'send'      => '<path d="m3 11 18-8-8 18-2.5-7.5L3 11z"/>',
        'chat'      => '<path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5z"/><path d="M9 11h.01"/><path d="M13 11h.01"/><path d="M17 11h.01"/>',
        'layers'    => '<path d="m12 3 9 5-9 5-9-5 9-5z"/><path d="m3 13 9 5 9-5"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M3 10h18"/>',
        'edit'      => '<path d="M4 20h4L20 8l-4-4L4 16v4z"/><path d="m14 6 4 4"/>',
        'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'cpu'       => '<rect x="6" y="6" width="12" height="12" rx="2"/><rect x="10" y="10" width="4" height="4"/><path d="M9 2v4"/><path d="M15 2v4"/><path d="M9 18v4"/><path d="M15 18v4"/><path d="M2 9h4"/><path d="M2 15h4"/><path d="M18 9h4"/><path d="M18 15h4"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18z"/>',
        'whatsapp'  => '<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.7-1.2A9 9 0 1 0 12 3z"/><path d="M9 8.5c-.5 2.5 3.5 6.7 6.2 6.6l.8-1.6-2-1.2-1 .8c-1-.4-2-1.4-2.4-2.4l.8-1-1.2-2-1.2.8z"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.2 6.8h.01"/>',
        'facebook'  => '<path d="M14 8h3V4h-3a5 5 0 0 0-5 5v3H6v4h3v6h4v-6h3l1-4h-4V9a1 1 0 0 1 1-1z"/>',
        'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7"/><path d="M8 7v.01"/><path d="M12 17v-4a3 3 0 0 1 6 0v4"/>',
        'tiktok'    => '<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4c.5 2.5 2.3 4.2 5 4.5"/>',
        'twitter'   => '<path d="m4 4 7.5 9.5L4.5 20h2l6-5.3L16.8 20H20l-7.8-9.9L19.3 4h-2l-5.4 4.8L8 4H4z"/>',
        'youtube'   => '<rect x="2.5" y="6" width="19" height="12.5" rx="3.5"/><path d="m10.5 9.5 5 2.8-5 2.8v-5.6z"/>',
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'sparkle'   => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/><path d="M19 16l.9 2.1L22 19l-2.1.9L19 22l-.9-2.1L16 19l2.1-.9L19 16z"/>',
        'filter'    => '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/>',
        'card'      => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/><path d="M6 15h4"/>',
        'loop'      => '<path d="M4 12a8 8 0 0 1 13.7-5.7L20 8"/><path d="M20 4v4h-4"/><path d="M20 12a8 8 0 0 1-13.7 5.7L4 16"/><path d="M4 20v-4h4"/>',
        'heart'     => '<path d="M12 20.5s-7.5-4.7-9.3-9A5.2 5.2 0 0 1 12 6.9a5.2 5.2 0 0 1 9.3 4.6c-1.8 4.3-9.3 9-9.3 9z"/>',
        'book'      => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v3H6.5"/>',
        'lock'      => '<rect x="4.5" y="10" width="15" height="10.5" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><circle cx="12" cy="15.2" r="1.4" fill="currentColor" stroke="none"/>',
        'image'     => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'video'     => '<path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>',
        'file'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
        'copy'      => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        'trash'     => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    );
    $body = isset($paths[$name]) ? $paths[$name] : $paths['sparkle'];
    return '<svg class="icon icon-' . esc($name) . '" width="' . (int) $size . '" height="' . (int) $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $body . '</svg>';
}

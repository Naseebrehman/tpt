<?php
/**
 * The Pie Technologies — Web Development service page
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'web-development',
    'title' => 'Websites That Work As Hard As You Do.',
    'lead'  => 'Fast, secure, conversion-engineered websites — landing pages, business sites, e-commerce and web apps. Built to load in under a second and turn traffic into pipeline.',
    'seoTitle' => 'Web Development Agency | Fast, Conversion-Focused Websites',
    'seoDesc'  => 'Landing pages, business sites, e-commerce, web apps, WordPress and Shopify builds. Sub-second load times, 98+ Lighthouse scores and 100% mobile responsive — always.',
    'intro'   => array(
        'heading'    => 'What we build',
        'title'      => 'A website is a sales employee. We hire it properly.',
        'paragraphs' => array(
            'Beautiful websites that don\'t convert are expensive brochures. We design and build around the action you need — a purchase, a booking, an enquiry — and we engineer the speed, structure and copy that make people take it.',
            'Every build ships with clean semantic code, Core Web Vitals in the green, accessibility baked in and an admin panel your team can actually use. No bloated page builders, no 40-plugin spaghetti.',
        ),
        'features' => array(
            array('icon' => 'target',  'title' => 'Landing Pages',    'text' => 'Single-purpose pages for campaigns: one goal, zero distractions, message-matched to the ad that sent the click.'),
            array('icon' => 'globe',   'title' => 'Business Sites',   'text' => 'Multi-page corporate sites with clear architecture, SEO-ready markup and a CMS your team won\'t fight.'),
            array('icon' => 'grid',    'title' => 'E-Commerce',       'text' => 'Stores built for conversion: fast product pages, frictionless checkout and upsell flows that lift order value.'),
            array('icon' => 'cpu',     'title' => 'Web Apps',         'text' => 'Dashboards, portals and custom tools — PHP/MySQL or JavaScript stacks, built clean and documented.'),
            array('icon' => 'edit',    'title' => 'WordPress',        'text' => 'Custom themes (not bought templates) with lightweight blocks, so editing stays easy and the site stays fast.'),
            array('icon' => 'layers',  'title' => 'Shopify',          'text' => 'Theme customisation, app rationalisation and CRO-focused product pages for Shopify merchants.'),
        ),
    ),
    'platformsTitle' => 'The stack we build on.',
    'platforms' => array(
        array('icon' => 'code',   'label' => 'HTML / CSS'),
        array('icon' => 'cpu',    'label' => 'PHP'),
        array('icon' => 'zap',    'label' => 'JavaScript'),
        array('icon' => 'edit',   'label' => 'WordPress'),
        array('icon' => 'grid',   'label' => 'Shopify'),
        array('icon' => 'layers', 'label' => 'MySQL'),
    ),
    'steps' => array(
        array('title' => 'Discovery',  'text' => 'Goals, users, competitors and the one action the site must drive. Sitemap and scope locked.'),
        array('title' => 'Wireframe',  'text' => 'Low-fi structure first: hierarchy, flow and conversion path before a single pixel is styled.'),
        array('title' => 'Design',     'text' => 'High-fidelity UI in your brand system, designed mobile-first and reviewed on real devices.'),
        array('title' => 'Build',      'text' => 'Clean, hand-written front-end and CMS integration. Semantic HTML, lazy media, zero junk scripts.'),
        array('title' => 'Test',       'text' => 'Cross-browser, cross-device, forms, speed and accessibility QA with a written test log.'),
        array('title' => 'Launch',     'text' => 'DNS, SSL, redirects, analytics, pixel and Search Console — flipped without losing rankings.'),
        array('title' => 'Support',    'text' => 'Monitoring, backups, updates and a retainer option for continuous improvement.'),
    ),
    'stats' => array(
        array('value' => 98,  'suffix' => '',  'label' => 'Average Lighthouse performance score'),
        array('value' => 0.9, 'decimals' => 1, 'suffix' => 's', 'label' => 'Median load time on 4G'),
        array('value' => 100, 'suffix' => '%', 'label' => 'Mobile responsive, every breakpoint'),
    ),
    'metrics' => array(
        array('value' => '<1s',   'label' => 'Load time target on mid-range mobile over 4G — because every extra second costs you ~7% of conversions.'),
        array('value' => '98',    'label' => 'Lighthouse performance score we design and build to, verified before launch and after every release.'),
        array('value' => '100%',  'label' => 'Mobile responsive from 320px to 2560px, tested on real devices — not just a browser resize.'),
    ),
    'gallery' => true,
    'testimonial' => true,
    'faq' => array(
        array('q' => 'How long does a website take to build?', 'a' => 'A focused landing page: 1–2 weeks. A full business site: 3–5 weeks. E-commerce or custom web apps: 6–12 weeks depending on catalogue size and integrations. You get a dated milestone plan at kickoff, and we hit it.'),
        array('q' => 'WordPress, Shopify or custom — which is right for me?', 'a' => 'Content-led business site: WordPress with a custom theme. Product catalogue and payments: Shopify. Unusual logic, portals or dashboards: custom PHP/JS. We recommend based on what you need to run in year two, not what\'s quickest to sell.'),
        array('q' => 'Will I be able to edit it myself?', 'a' => 'Yes. Every build ships with an admin area scoped to exactly what you should change — text, images, blog posts, products — with the structural parts locked so the design can\'t be broken by accident.'),
        array('q' => 'Can you rebuild my slow existing site without losing SEO?', 'a' => 'That\'s our favourite project. We map every ranking URL, build 301 redirects, preserve on-page signals and usually ship the new site faster and higher-ranking than the old one. Traffic dips are prevented, not recovered.'),
        array('q' => 'Do you handle hosting and domain setup?', 'a' => 'We\'ll recommend hosting that matches your traffic and budget, configure SSL, CDN, caching and backups, and handle the DNS cutover. You keep ownership of every account and credential.'),
        array('q' => 'What happens after launch?', 'a' => '30 days of post-launch fixes are included. After that, most clients take a care plan: monitoring, updates, backups and a monthly block of improvement work driven by analytics — because a website is never finished.'),
    ),
    'cta' => array(
        'title'  => 'Let\'s build your next website.',
        'text'   => 'Tell us the goal and we\'ll come back with a scope, a timeline and a fixed quote — free.',
        'button' => 'Scope My Project',
    ),
);

require dirname(__DIR__) . '/includes/service-page.php';

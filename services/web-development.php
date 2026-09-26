<?php
/**
 * The Pie Technologies — Web Development service page (BUILD)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'web-development',
    'title' => 'Web Development',
    'lead'  => 'Websites built as sales assets — fast, secure, and measured on conversion.',
    'seoTitle' => 'Web Development — Custom Websites, eCommerce & CMS | TPT',
    'seoDesc'  => 'Custom responsive websites, eCommerce stores and CMS builds engineered for speed, security and SEO-ready architecture — with maintenance that keeps the asset compounding.',
    'heroDesc' => 'A brochure website costs you every day it fails to convert. We build sales assets: fast loads, clear paths, forms and journeys designed around one question — did the visitor take the action the business needed? Then we instrument it so the answer is measurable.',
    'bullets'  => array(
        'Custom design and build — no recycled templates',
        'Mobile-first, tested from 375px to desktop',
        'Speed and Core Web Vitals engineered in, not bolted on',
        'SEO-ready architecture and analytics from day one',
    ),
    'cta' => array(
        'title'  => 'Is your website a brochure or a salesperson?',
        'text'   => 'Get a website conversion review: speed, mobile experience, journey clarity and what visitors do before they leave — with the fixes ranked by revenue impact.',
        'button' => 'Get a Website Conversion Review',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'Beautiful sites that don’t sell.',
        'paragraphs' => array(
            'The graveyard of web projects is full of sites that look great and perform quietly: three seconds to first paint, forms nobody fills, content that can’t be edited without calling a developer, and analytics that track nothing. Traffic arrives — from ads you’re paying for — and leaks away.',
            'A website is the conversion layer under every other channel you run. When it’s weak, every marketing dollar is taxed. When it’s built as a system — fast, clear, instrumented — the same traffic produces measurably more pipeline.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'The site is slow on phones, where most visitors actually are',
            'Content changes require a developer or a support ticket',
            'Traffic comes in; nobody can say where it goes or why it leaves',
            'Forms capture leads that arrive late, duplicated or untracked',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From visitor to customer.',
        'lead'    => 'Every stage of the site is designed as a conversion system.',
        'steps'   => array(
            array('title' => 'Arrive',   'text' => 'Sub-second loads and mobile-first layout — speed is the first conversion rate optimization.'),
            array('title' => 'Orient',   'text' => 'Within five seconds: what you do, for whom, and why you. Clear architecture, no maze.'),
            array('title' => 'Trust',    'text' => 'Proof placed where doubt forms — work, testimonials, credentials, real photos.'),
            array('title' => 'Act',      'text' => 'One primary action per page: form, call, booking or cart — designed to qualify, not just collect.'),
            array('title' => 'Route',    'text' => 'Submissions routed instantly to the right human or system, with confirmations that set expectations.'),
            array('title' => 'Measure',  'text' => 'Every journey tracked: what converted, where visitors left, what to fix next.'),
        ),
        'note' => 'We ship with analytics, search console and uptime monitoring already wired — a site you can’t measure is a site you can’t improve.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The web build stack.',
    'pillarsLead'    => 'Four workstreams, from first wireframe to long-term care:',
    'pillars' => array(
        array('title' => 'Design & build', 'text' => 'Custom, responsive, conversion-shaped.', 'points' => array(
            'Custom UI/UX designed around your buyer’s journey',
            'Responsive development tested from 375px to ultrawide',
            'CMS integration so your team edits without developers',
            'eCommerce builds with clean product and checkout flows',
        )),
        array('title' => 'Performance', 'text' => 'Speed engineered in, verified after.', 'points' => array(
            'Core Web Vitals budgets enforced during development',
            'Image, font and script loading optimized',
            'Caching and CDN strategy per hosting environment',
            'Performance tested on real mobile devices, not just lab scores',
        )),
        array('title' => 'Security & foundations', 'text' => 'Boring, decisive, non-negotiable.', 'points' => array(
            'SSL, hardened configurations and dependency hygiene',
            'Input validation and prepared statements across every form',
            'Backups, updates and a disaster-recovery runbook',
            'Role-based admin access — least privilege by default',
        )),
        array('title' => 'SEO & integration', 'text' => 'Built to be found and to connect.', 'points' => array(
            'SEO-ready architecture: URLs, sitemaps, schema, metas',
            'Analytics, Search Console and conversion tracking wired at launch',
            'CRM, payment, booking and third-party API integrations',
            'Lead routing so submissions reach the right human instantly',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'From kickoff to launch.',
    'timeline' => array(
        array('when' => 'Week 1–2', 'title' => 'Discovery',      'text' => 'Goals, audience, journey mapping and content audit. Sitemap and wireframes agreed before pixels exist.'),
        array('when' => 'Week 2–4', 'title' => 'Design',         'text' => 'Custom UI designed page by page, reviewed on real content — not lorem ipsum — and approved before build.'),
        array('when' => 'Week 4–7', 'title' => 'Build',          'text' => 'Responsive development, CMS integration, forms and routing. Performance budgets enforced as we go.'),
        array('when' => 'Week 7–8', 'title' => 'Instrument',     'text' => 'Analytics, Search Console, conversion tracking, schema and security hardening verified end to end.'),
        array('when' => 'Week 8–9', 'title' => 'Launch',         'text' => 'QA across devices and browsers, redirects from the old site, DNS and SSL handled, launch checklist executed.'),
        array('when' => 'Ongoing',  'title' => 'Care & improve', 'text' => 'Maintenance plans: updates, backups, monitoring — plus conversion improvements from real behavior data.'),
    ),
    'faqTitle' => 'Web development, straight answers.',
    'faq' => array(
        array('q' => 'WordPress, custom code, or something else?', 'a' => 'Whatever serves the goals — we recommend after discovery. WordPress fits content-heavy sites and teams that want easy editing; custom builds fit performance-critical or integration-heavy projects; e-commerce gets its own decision. We build in all three and don’t sell you a technology we don’t believe in for your case.'),
        array('q' => 'Can you work with our existing site?', 'a' => 'Yes. Many projects are rescues and upgrades rather than rebuilds: we audit what exists, keep what works, and rebuild only the parts that cost you conversions or speed. When a rebuild is genuinely cheaper than patching, we’ll show you the numbers.'),
        array('q' => 'Who owns the website when it’s done?', 'a' => 'You do — code, content, design files, domain, hosting and every credential. We hand over documentation and training. No lock-in, no hostage situations, no proprietary black boxes.'),
        array('q' => 'What happens after launch?', 'a' => 'Two things: care and improvement. Care means updates, backups, monitoring and security hygiene. Improvement means using the analytics we wired at launch — where visitors leave, what converts — to ship changes that lift the site’s business results month over month.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint',  'title' => 'The Website Conversion Blueprint', 'note' => 'The conversion checklist we build to, free', 'url' => url('resources/website-conversion-blueprint')),
        array('label' => 'Checklist',  'title' => 'Website Launch Checklist',         'note' => 'Everything verified before you go live', 'url' => url('resources/website-launch-checklist')),
        array('label' => 'Case study', 'title' => 'Lead Generation Engine',           'note' => 'Landing pages that close the loop on ads', 'url' => url('portfolio/lead-generation-engine-meta-ads')),
    ),
    'deliverables' => array(
        'Custom responsive website design and development',
        'CMS integration with team training',
        'eCommerce setup with product, cart and payment flows',
        'Core Web Vitals and mobile performance optimization',
        'Security hardening, backups and monitoring setup',
        'SEO-ready architecture with schema and sitemaps',
        'CRM, booking and third-party integrations',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('graphic-design', 'seo', 'data-analytics'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

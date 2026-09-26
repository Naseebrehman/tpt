<?php
/**
 * The Pie Technologies — App Development service page (BUILD)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'app-development',
    'title' => 'App Development',
    'lead'  => 'Mobile apps that earn their place on the home screen.',
    'seoTitle' => 'App Development — iOS, Android & Cross-Platform | TPT',
    'seoDesc'  => 'Custom iOS and Android apps: discovery, UX design, cross-platform development, API integration, store publishing and ongoing updates — built around retention, not just launch.',
    'heroDesc' => 'Apps fail in two ways: built without a retention plan, or never shipped because scope grew forever. We start from the behavior you want to own — booking, ordering, loyalty, field work — design the shortest path to it, and launch on both stores with the analytics to prove it works.',
    'bullets'  => array(
        'iOS and Android from one cross-platform codebase where it fits',
        'UX designed around the behavior you want to repeat',
        'Store publishing handled — review, compliance and all',
        'Analytics and crash reporting wired before launch',
    ),
    'cta' => array(
        'title'  => 'App idea, or app-shaped problem?',
        'text'   => 'Get an app discovery session: we pressure-test the concept, map the shortest build to the behavior you want, and give you a phased plan with honest scope. Sometimes the right answer is a web app — we’ll tell you that too.',
        'button' => 'Book App Discovery',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'Most apps are launched, not adopted.',
        'paragraphs' => array(
            'The app stores are full of beautiful products nobody opens twice: built from a feature list instead of a behavior, shipped without onboarding that earns the second session, and abandoned when v2 needed what v1 should have measured.',
            'An app is only worth building when there’s a repeated action you want to own — bookings, orders, check-ins, field reports. Start from that action, design the shortest path to it, and instrument everything. The rest is engineering discipline.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'A feature list from a committee, no clear user behavior',
            'Quotes that balloon because scope was never phased',
            'An existing app with crashes nobody can diagnose',
            'Downloads at launch, retention near zero by month two',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From idea to installed — and kept.',
        'lead'    => 'Six stages, with adoption designed in from the first.',
        'steps'   => array(
            array('title' => 'Define',   'text' => 'The core behavior, the user, and the shortest path between them. Features earn their place against that path.'),
            array('title' => 'Design',   'text' => 'Flows and UI prototyped and tested on real devices before engineering starts.'),
            array('title' => 'Build',    'text' => 'Cross-platform or native, chosen on evidence — with backend, APIs and offline behavior handled properly.'),
            array('title' => 'Harden',   'text' => 'Crash reporting, analytics, security review and device-matrix testing before anyone outside sees it.'),
            array('title' => 'Ship',     'text' => 'Store listings, review compliance, staged rollout — iOS and Android published, not just uploaded.'),
            array('title' => 'Retain',   'text' => 'Onboarding, push strategy and release cadence driven by the analytics we wired in — adoption compounds deliberately.'),
        ),
        'note' => 'We phase every build: v1 proves the behavior, v2 scales it. Nobody should finance a twelve-month big-bang app.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The app build stack.',
    'pillarsLead'    => 'Four workstreams, from concept to continuous release:',
    'pillars' => array(
        array('title' => 'Discovery & UX', 'text' => 'The behavior first, the pixels second.', 'points' => array(
            'Concept pressure-testing and build-vs-alternative analysis',
            'User flows mapped to the core repeated behavior',
            'UI/UX designed and prototyped for iOS and Android idioms',
            'Onboarding designed to earn the second session',
        )),
        array('title' => 'Development', 'text' => 'One codebase where it fits, native where it matters.', 'points' => array(
            'Cross-platform builds (React Native / Flutter) when they fit',
            'Native modules where performance or hardware demands it',
            'Backend, APIs and databases designed for the app’s real load',
            'Offline behavior and sync handled deliberately',
        )),
        array('title' => 'Quality & security', 'text' => 'Hardened before it’s public.', 'points' => array(
            'Crash reporting and analytics wired from the first build',
            'Device and OS-version matrix testing',
            'Secure storage, transport encryption and auth review',
            'Store compliance: privacy manifests, permissions, policies',
        )),
        array('title' => 'Launch & lifecycle', 'text' => 'Publishing is the start, not the finish.', 'points' => array(
            'App Store and Google Play submission, review and rollout',
            'Push notification strategy that informs without annoying',
            'Release cadence with changelogs your users actually read',
            'Maintenance, OS-compatibility updates and store requirement changes',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'A typical v1 arc.',
    'timeline' => array(
        array('when' => 'Week 1–2',  'title' => 'Discovery',      'text' => 'Behavior definition, platform decision, phased scope and honest budget model. Sometimes the answer is “web app first” — we’ll say so.'),
        array('when' => 'Week 3–5',  'title' => 'UX & prototype', 'text' => 'Flows, wireframes and interactive prototype tested with real users on real devices.'),
        array('when' => 'Week 6–12', 'title' => 'Build v1',       'text' => 'Core behavior, backend, integrations and analytics — developed in reviewable increments you can see weekly.'),
        array('when' => 'Week 12–14','title' => 'Harden & beta',  'text' => 'Device-matrix testing, crash and security review, closed beta with seeded users.'),
        array('when' => 'Week 14–16','title' => 'Store launch',   'text' => 'Listings, screenshots, review submission and staged rollout on both stores.'),
        array('when' => 'Ongoing',   'title' => 'Retain & evolve','text' => 'Analytics-driven releases, push strategy tuning, OS updates and store-compliance maintenance.'),
    ),
    'faqTitle' => 'App development, straight answers.',
    'faq' => array(
        array('q' => 'Do we need native iOS and Android apps?', 'a' => 'Often not. Cross-platform frameworks ship both stores from one codebase at materially lower cost, and the performance gap has mostly closed. We recommend native only when your app genuinely needs it — heavy graphics, deep hardware use, background processing — and we show the trade-off honestly.'),
        array('q' => 'Should we build an app or improve our website first?', 'a' => 'If the behavior you want is occasional — browsing, reading, buying once — a fast website or PWA usually wins. Apps earn their cost when there’s a repeated action: bookings, check-ins, orders, field work. We pressure-test your case in discovery and will tell you if an app is premature.'),
        array('q' => 'Who owns the app and its accounts?', 'a' => 'You do — code repository, store developer accounts, signing keys and backend credentials are yours from day one. We build in your accounts where possible, and hand over everything documented at launch.'),
        array('q' => 'What does ongoing maintenance actually cover?', 'a' => 'OS version compatibility every year, store requirement changes, dependency and security updates, crash triage, and the analytics review that drives your next release. Apps are products with lifecycles, not documents you print once.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint', 'title' => 'The Website Conversion Blueprint', 'note' => 'Conversion principles apps share with web', 'url' => url('resources/website-conversion-blueprint')),
        array('label' => 'Service',   'title' => 'Web Development',                  'note' => 'The faster path when an app is premature', 'url' => url('services/web-development')),
        array('label' => 'Service',   'title' => 'Data Analytics',                   'note' => 'Measure retention from day one', 'url' => url('services/data-analytics')),
    ),
    'deliverables' => array(
        'App discovery, platform recommendation and phased scope',
        'UX flows and interactive prototypes tested on devices',
        'Cross-platform or native iOS/Android development',
        'Backend, API and database architecture',
        'Crash reporting, analytics and security hardening',
        'App Store and Google Play publishing, handled end to end',
        'Push notification strategy and ongoing release maintenance',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('web-development', 'graphic-design', 'data-analytics'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

<?php
/**
 * The Pie Technologies — Branding & Design service page
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'branding-design',
    'title' => 'Look Like the Market Leader — Before You Are One.',
    'lead'  => 'Identity systems, logos, guidelines and creative direction that make every touchpoint feel intentional. Brand work that makes your paid traffic convert harder.',
    'seoTitle' => 'Branding & Design Agency | Identities Built to Scale',
    'seoDesc'  => 'Logo and identity design, brand guidelines, creative direction, packaging and social kits. A brand system that makes every ad, post and page feel like one company.',
    'intro'   => array(
        'heading'    => 'What we do',
        'title'      => 'Brand is the multiplier on every other channel.',
        'paragraphs' => array(
            'The same ad from a trusted-looking brand outperforms the same ad from a sloppy one — usually by a wide margin. Branding isn\'t decoration; it\'s the reason people believe your price.',
            'We build identity systems, not just logos: type, colour, voice, imagery rules and the guidelines that keep every future asset on-brand without you in the room.',
        ),
        'features' => array(
            array('icon' => 'pen',     'title' => 'Logo & Identity',     'text' => 'A primary mark, responsive variants and a visual system designed to work from favicon to billboard.'),
            array('icon' => 'layers',  'title' => 'Brand Guidelines',    'text' => 'A practical rulebook — type, colour, spacing, voice, do/don\'t — that keeps every asset consistent.'),
            array('icon' => 'sparkle', 'title' => 'Creative Direction',  'text' => 'Art direction for shoots, campaigns and content so your brand looks like one hand made it.'),
            array('icon' => 'grid',    'title' => 'Packaging & Print',   'text' => 'Labels, boxes, menus and collateral designed for shelf impact and print production reality.'),
            array('icon' => 'instagram','title' => 'Social Media Kits',  'text' => 'Template systems for feeds, stories and ads — on-brand content your team can produce fast.'),
            array('icon' => 'zap',     'title' => 'Brand Refresh',       'text' => 'Evolution, not demolition: modernise an existing brand without throwing away its equity.'),
        ),
    ),
    'steps' => array(
        array('title' => 'Discovery', 'text' => 'Stakeholder interviews, audience and competitor mapping, positioning workshop.'),
        array('title' => 'Strategy',  'text' => 'Positioning statement, personality, voice and the one idea the brand owns.'),
        array('title' => 'Design',    'text' => 'Logo routes, type and colour systems presented with rationale — not mood boards.'),
        array('title' => 'System',    'text' => 'Guidelines, templates and asset library built for the teams who\'ll use them.'),
        array('title' => 'Rollout',   'text' => 'Launch kit: social profiles, website skin, ad templates and print-ready masters.'),
    ),
    'stats' => array(
        array('value' => 60,  'suffix' => '+',   'label' => 'Brand identities designed and shipped'),
        array('value' => 2,   'suffix' => ' wks', 'label' => 'Average sprint from kickoff to first routes'),
        array('value' => 100, 'suffix' => '%',   'label' => 'Of deliverables arrive on-brand, on-time'),
    ),
    'who' => array(
        array('icon' => 'rocket', 'title' => 'Startups Launching',   'text' => 'Founders who need to look fundable and trustworthy from day one — investors judge the deck\'s design too.'),
        array('icon' => 'chart',  'title' => 'Brands Scaling',       'text' => 'Companies outgrowing their DIY identity, right when consistency starts paying for itself.'),
        array('icon' => 'refresh', 'title' => 'Legacy Rebrands',     'text' => 'Established businesses modernising without losing the recognition they spent years earning.'),
    ),
    'testimonial' => true,
    'faq' => array(
        array('q' => 'What exactly do we receive at the end?', 'a' => 'A complete package: primary and secondary logos in all formats (SVG, PNG, EPS), colour and type systems, brand guidelines document, social kit templates and the source files. Everything organised so any designer or printer can pick it up.'),
        array('q' => 'How many design concepts will we see?', 'a' => 'Two to three fully-developed routes, each with rationale tied back to strategy. Endless "logo options" are a symptom of missing strategy — we\'d rather show you fewer, better-considered directions.'),
        array('q' => 'How involved do we need to be?', 'a' => 'About three working sessions: kickoff workshop, strategy read-out and design presentation. Between those, we work. Decisions from one or two stakeholders keep the process fast and the brand coherent.'),
        array('q' => 'Can you work with our existing logo?', 'a' => 'Yes. A refresh keeps the equity you\'ve built and fixes what\'s holding you back — legibility, scalability, dated execution. We\'ll show you side-by-side what changes and what stays, and why.'),
        array('q' => 'Does branding actually affect ad performance?', 'a' => 'Measurably. Consistent, premium presentation lifts landing-page conversion and ad click-through because trust lowers friction. Several of our clients saw CPA drop after rebrand with media strategy unchanged.'),
        array('q' => 'How long does a full brand project take?', 'a' => 'Typically 4–6 weeks end to end: one week discovery and strategy, two weeks design, then system building and rollout. Rush timelines are possible with a scoped-down rollout.'),
    ),
    'cta' => array(
        'title'  => 'Start Your Brand Project.',
        'text'   => 'Tell us where the brand is today and where it needs to look like it belongs — we\'ll map the route.',
        'button' => 'Book a Brand Call',
    ),
);

require dirname(__DIR__) . '/includes/service-page.php';

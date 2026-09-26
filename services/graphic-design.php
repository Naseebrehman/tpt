<?php
/**
 * The Pie Technologies — Graphic Design service page (CREATE)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'graphic-design',
    'title' => 'Graphic Design',
    'lead'  => 'Brand systems that make every channel look like the same company — because it is.',
    'seoTitle' => 'Graphic Design — Brand Identity, Logo & Marketing Assets | TPT',
    'seoDesc'  => 'Brand identity, logo design, marketing collateral, social assets, UI design and print — delivered as a system with guidelines your whole team can execute against.',
    'heroDesc' => 'Design isn’t decoration; it’s the trust layer under everything you sell. People judge the ad, the website and the proposal by the same visual system. We build that system once — logo, type, color, layout rules — then produce every asset from it, so the brand compounds instead of drifting.',
    'bullets'  => array(
        'Identity built from positioning, not Pinterest',
        'Brand guidelines your team can actually execute',
        'Asset systems: social, ads, decks, print — one language',
        'Files organized and handed over, source included',
    ),
    'cta' => array(
        'title'  => 'Does every channel look like the same company?',
        'text'   => 'Get a brand consistency review: your logo, site, ads and collateral side by side, with the drift marked and a system proposed to end it.',
        'button' => 'Get a Brand Review',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'Visual drift is a trust leak.',
        'paragraphs' => array(
            'Without a system, every asset is a negotiation: the social posts use three fonts, the proposal deck is from 2021, the ads look like a different company than the website, and the logo exists in seven slightly wrong versions. Buyers notice inconsistency before they can name it — and they price it as risk.',
            'The fix is a brand system, not a rebrand event. One identity, documented, with templates for everything you produce weekly. Then consistency stops depending on whoever remembered to ask.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Nobody knows which logo file is the current one',
            'Social, ads and website styled by three different hands',
            'A brand that looks five years behind the competitors',
            'Every new asset designed from scratch, slowly',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From positioning to production.',
        'lead'    => 'A design system, then a factory that runs on it.',
        'steps'   => array(
            array('title' => 'Position',  'text' => 'Who you are, for whom, against whom. Design decisions start from strategy or they’re just taste.'),
            array('title' => 'Define',    'text' => 'Logo, type, color, imagery and layout rules — the system, documented so it survives handoffs.'),
            array('title' => 'Prove',     'text' => 'The system applied to real assets: a landing page, an ad set, a deck. Design must work in production.'),
            array('title' => 'Template',  'text' => 'Reusable templates for everything you produce weekly — social, ads, proposals, documents.'),
            array('title' => 'Produce',   'text' => 'Ongoing asset production from the system, on brand without renegotiation.'),
            array('title' => 'Govern',    'text' => 'Guidelines, file libraries and review rules keep the brand from drifting as the team grows.'),
        ),
        'note' => 'We hand over source files and templates organized by use — a brand system your team owns, not a folder of flattened JPEGs.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The design operating stack.',
    'pillarsLead'    => 'Four workstreams, from identity to weekly production:',
    'pillars' => array(
        array('title' => 'Brand identity', 'text' => 'The system everything else runs on.', 'points' => array(
            'Logo design and refinement, with usage rules',
            'Type, color and imagery systems chosen for every medium',
            'Brand guidelines written for humans, not designers only',
            'Voice and tone aligned with the visual system',
        )),
        array('title' => 'Marketing assets', 'text' => 'The weekly factory, on system.', 'points' => array(
            'Ad creative sets for Meta, Google and LinkedIn formats',
            'Social media templates: posts, carousels, reels covers, stories',
            'Decks, one-pagers and proposal templates that sell',
            'Email headers, banners and campaign assets',
        )),
        array('title' => 'Digital & print', 'text' => 'One brand across every surface.', 'points' => array(
            'Website and app UI design handed to development cleanly',
            'Packaging, labels and product materials',
            'Print collateral: brochures, signage, stationery, merch',
            'Photo direction and asset libraries',
        )),
        array('title' => 'Governance & handoff', 'text' => 'Consistency that survives growth.', 'points' => array(
            'Organized file libraries with versioning discipline',
            'Templates your team can self-serve safely',
            'Review checkpoints for high-stakes assets',
            'Onboarding docs so new hires stay on brand in week one',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'A typical identity engagement.',
    'timeline' => array(
        array('when' => 'Week 1',    'title' => 'Positioning',    'text' => 'Workshops and research: audience, competitors, current perception. Design brief agreed in writing.'),
        array('when' => 'Week 2–3',  'title' => 'Concept',        'text' => 'Identity directions explored — presented applied to real assets, never as floating logos on white.'),
        array('when' => 'Week 4',    'title' => 'Refine',         'text' => 'One direction refined: system, rules, edge cases, accessibility and reproduction tests.'),
        array('when' => 'Week 5',    'title' => 'Document',       'text' => 'Guidelines, file libraries and templates delivered; team trained on self-serve use.'),
        array('when' => 'Week 6+',   'title' => 'Roll out',       'text' => 'Applied across website, social, ads and print in priority order — old assets retired deliberately.'),
        array('when' => 'Ongoing',   'title' => 'Produce',        'text' => 'Monthly asset production from the system, with governance keeping everything on brand.'),
    ),
    'faqTitle' => 'Graphic design, straight answers.',
    'faq' => array(
        array('q' => 'Do we need a full rebrand, or just cleanup?', 'a' => 'Start with the audit. If your positioning has changed or the identity actively costs you trust, rebrand deliberately. If the identity is sound but execution has drifted, a system refresh — guidelines, templates, file hygiene — fixes 80% of the problem at 20% of the cost. We recommend based on evidence, not billable ambition.'),
        array('q' => 'What do we actually receive at the end?', 'a' => 'The full system: logo files in every format and usage, type and color specifications, brand guidelines, template libraries for your weekly assets, and organized source files. Everything documented so your team — or any future vendor — can execute without guessing.'),
        array('q' => 'Can you match our existing brand?', 'a' => 'Yes. We reverse-engineer existing identities into proper systems all the time: documenting what was never written down, filling gaps (dark modes, social formats, print specs) and templating the output. The brand stays recognizable; the chaos stops.'),
        array('q' => 'How do design and our marketing results connect?', 'a' => 'Directly, and we measure it: ad creative is tested like any other variable, landing-page design decisions ship with analytics attached, and template systems speed up campaign production. Design that can’t be tied to a business outcome is decoration — we build assets with jobs.'),
    ),
    'deeper' => array(
        array('label' => 'Playbook', 'title' => 'Meta Ads Creative Testing Playbook', 'note' => 'How designed creative gets tested', 'url' => url('resources/meta-ads-creative-testing-playbook')),
        array('label' => 'Blueprint','title' => 'Social Media Growth Blueprint',      'note' => 'The content system design feeds', 'url' => url('resources/social-media-growth-blueprint')),
        array('label' => 'Service',  'title' => 'Web Development',                    'note' => 'Design shipped as a live product', 'url' => url('services/web-development')),
    ),
    'deliverables' => array(
        'Brand identity: logo, type, color and imagery systems',
        'Brand guidelines written for real-world execution',
        'Marketing collateral: decks, one-pagers, proposals',
        'Social media asset templates and ad creative sets',
        'Website and app UI design',
        'Print and packaging design where needed',
        'Organized file libraries with team handoff and training',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('social-media-management', 'web-development', 'meta-ads'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

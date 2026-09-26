<?php
/**
 * The Pie Technologies — Local SEO service page (GET FOUND)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'local-seo',
    'title' => 'Local SEO',
    'lead'  => 'Own the map pack in the areas that pay you.',
    'seoTitle' => 'Local SEO & Google Business Profile Management | TPT',
    'seoDesc'  => 'Google Business Profile, reviews, citations, location pages and local schema — systemized so "near me" searches turn into calls from the neighborhoods you actually serve.',
    'heroDesc' => 'Google Business Profile, reviews, citations, location pages and local schema — systemized so “near me” searches turn into calls from the neighborhoods you actually serve.',
    'bullets'  => array(
        'Google Business Profile, professionally run',
        'Reviews that arrive every week',
        'Every service area covered',
        'Calls tracked back to the source',
    ),
    'cta' => array(
        'title'  => 'Invisible on the map while competitors take the calls?',
        'text'   => 'Get a local visibility review: your profile, reviews, citations and rankings by area — with the gaps marked. You’ll know exactly why the phone isn’t ringing.',
        'button' => 'Get a Local Visibility Review',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'The map is the new front page.',
        'paragraphs' => array(
            'For local businesses, “near me” is the moment of truth. Three businesses get the map pack; everyone else gets the scraps. The winners aren’t always the best companies — they’re the most complete profiles with the most recent trust signals.',
            'A neglected profile with 14 reviews and wrong categories will lose to a worse competitor with 140 reviews and a managed presence. Every single day.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Competitors with worse reputations ranking above you',
            'A Business Profile that hasn’t been touched in months',
            'Reviews arriving randomly — or not at all',
            'No visibility in the towns you want to grow into',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From search to scheduled job.',
        'lead'    => 'Local SEO is a flywheel. We install it and keep it turning.',
        'steps'   => array(
            array('title' => 'Search',   'text' => 'Someone nearby needs what you do and reaches for their phone.'),
            array('title' => 'Map Pack', 'text' => 'Proximity, relevance and prominence decide the three who get seen. We work all three levers.'),
            array('title' => 'Profile',  'text' => 'Complete services, fresh photos, active posts, real Q&A. Your profile sells before anyone clicks.'),
            array('title' => 'Reviews',  'text' => 'Recent, specific, answered. The trust moment that tips the decision.'),
            array('title' => 'Call',     'text' => 'One tap. Tracked, so you know exactly which visibility produced it.'),
            array('title' => 'Job',      'text' => 'The job feeds the next review — the flywheel that keeps you on top.'),
        ),
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The local visibility stack.',
    'pillarsLead'    => 'Four workstreams, maintained continuously:',
    'pillars' => array(
        array('title' => 'Business Profile', 'text' => 'Your profile is a selling asset, not a directory entry.', 'points' => array(
            'Categories, services and descriptions mapped to search reality',
            'Photos and posts on a schedule — profiles age like food',
            'Q&A seeded and answered',
            'Spam fighting and listing protection',
        )),
        array('title' => 'Reviews', 'text' => 'A system that produces recent, specific, answered reviews — steadily.', 'points' => array(
            'Post-job review requests by SMS/email, timed right',
            'Response templates in your voice',
            'Negative review protocol that recovers trust',
            'Review velocity tracking — steady beats spiky',
        )),
        array('title' => 'Citations & consistency', 'text' => 'Clean NAP everywhere it matters, nowhere it hurts.', 'points' => array(
            'NAP audit and cleanup across directories',
            'New citations in the directories that matter',
            'Duplicate listing suppression',
            'Aggregators monitored',
        )),
        array('title' => 'On-site local', 'text' => 'Pages that prove you actually serve where you say you serve.', 'points' => array(
            'Service × area page architecture',
            'Local schema — LocalBusiness, service, FAQ',
            'Localized content that sounds like you work there',
            'Embedded maps, driving directions and proof',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'Ninety days to a working map presence.',
    'timeline' => array(
        array('when' => 'Days 1–14',  'title' => 'Baseline',         'text' => 'Grid-based ranking check across your service areas, profile audit, citation and review inventory.'),
        array('when' => 'Days 15–30', 'title' => 'Profile rebuild',  'text' => 'Categories, services, descriptions, photos, posts, Q&A — rebuilt as a selling asset.'),
        array('when' => 'Days 30–45', 'title' => 'Review engine',    'text' => 'Request flow installed with your team; responses templated; velocity goals set.'),
        array('when' => 'Days 45–60', 'title' => 'Coverage build',   'text' => 'Location and service-area pages ship with schema and tracking.'),
        array('when' => 'Days 60–75', 'title' => 'Authority',        'text' => 'Citations cleaned and built; local links and mentions pursued.'),
        array('when' => 'Ongoing',    'title' => 'Report',           'text' => 'Monthly grid scans, calls, direction requests and leads — by area.'),
    ),
    'faqTitle' => 'Local SEO, straight answers.',
    'faq' => array(
        array('q' => 'How long until we show in the map pack?', 'a' => 'Profile rebuilds can shift visibility in weeks. Owning competitive areas consistently is a 3–6 month flywheel — reviews and content compound. We show you the grid every month so progress is undeniable.'),
        array('q' => 'Can you get us reviewed in cities where we have no office?', 'a' => 'We build legitimate service-area visibility — pages, citations, content — for the areas you actually serve. We don’t use fake locations or PO boxes; that’s how listings get suspended.'),
        array('q' => 'What if we get a bad review?', 'a' => 'It happens to every business that serves real customers. Our protocol: respond within 24 hours, take it offline, fix what’s fixable, then bury it in honest volume. One bad review among many specifics reads as authenticity.'),
        array('q' => 'Do you also run Google Ads for local?', 'a' => 'Yes — Local Service Ads and Search pair well with map work, and we manage both. The blend depends on your market’s costs, which we review together first.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint',  'title' => 'The Local SEO Blueprint',              'note' => 'The full playbook, free', 'url' => url('resources/local-seo-blueprint')),
        array('label' => 'Case study', 'title' => 'Local Visibility System',               'note' => 'The system applied to a home services brand', 'url' => url('portfolio/local-visibility-system-home-services')),
        array('label' => 'Service',    'title' => 'Google Ads management',                 'note' => 'Paid coverage while the map compounds', 'url' => url('services/google-ads')),
    ),
    'deliverables' => array(
        'Google Business Profile optimization and management',
        'Review generation system and response protocols',
        'Citation building and NAP consistency cleanup',
        'Service-area and location page architecture',
        'Local schema markup',
        'Local content strategy',
        'Map pack tracking and call attribution',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('seo', 'google-ads', 'ai-business-optimization'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

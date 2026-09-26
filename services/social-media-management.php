<?php
/**
 * The Pie Technologies — Social Media Management service page (GROW)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'social-media-management',
    'title' => 'Social Media Management',
    'lead'  => 'An always-on social presence that sells trust before sales ever has to.',
    'seoTitle' => 'Social Media Management — Strategy, Content & Community | TPT',
    'seoDesc'  => 'Content strategy, calendars, design and video, community management and profile optimization across Instagram, Facebook, LinkedIn, TikTok and X — measured on business outcomes, not vanity metrics.',
    'heroDesc' => 'Social is the layer that makes every other channel cheaper: paid ads convert better against an active profile, and organic reach compounds into owned audiences. We run your presence like a department, not a side task.',
    'bullets'  => array(
        'Content pillars built from your actual business',
        'Calendars planned a month ahead, posted on schedule',
        'Design and video that fit each platform’s native language',
        'Community managed daily — comments, DMs, mentions',
    ),
    'cta' => array(
        'title'  => 'Posting more but growing slower?',
        'text'   => 'Get a social audit: profile, content mix, engagement patterns and what your competitors do better — with a plan for the next 90 days. Frequency is not strategy; we’ll show you the difference.',
        'button' => 'Get a Social Media Audit',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'An unmaintained profile is a live objection.',
        'paragraphs' => array(
            'Before anyone fills your form or answers your call, they check your socials. A profile last posted three months ago, with unanswered comments and stock-photo content, quietly taxes every campaign you run — people see it, doubt you, and leave.',
            'The fix isn’t posting more. It’s posting with intent: content pillars that map to how your business actually grows, formats native to each platform, and community management that turns attention into conversations.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Content posted randomly — whenever someone remembered',
            'Same post recycled across every platform, fitting none',
            'Comments and DMs sitting unanswered for days',
            'No idea which posts ever produced a customer',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From attention to conversation.',
        'lead'    => 'A weekly production loop with a business outcome attached.',
        'steps'   => array(
            array('title' => 'Listen',  'text' => 'Audience questions, competitor angles and platform trends reviewed weekly — content answers what people are actually asking.'),
            array('title' => 'Plan',    'text' => 'Monthly calendar mapped to content pillars: educate, prove, humanize, convert — with each post assigned a job.'),
            array('title' => 'Produce', 'text' => 'Designs, reels, carousels and copy produced in batches, on-brand and platform-native.'),
            array('title' => 'Publish', 'text' => 'Scheduled at researched times, with captions, hashtags and links QA’d per platform.'),
            array('title' => 'Engage',  'text' => 'Comments, DMs and mentions handled daily in your voice — every reply is a conversion opportunity.'),
            array('title' => 'Learn',   'text' => 'Monthly report: what earned reach, what sparked conversations, what drove clicks and leads — and what we change next month.'),
        ),
        'note' => 'We measure social on business outcomes — profile visits, link clicks, DM conversations, leads — not on follower-count theater.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The social operating stack.',
    'pillarsLead'    => 'Four workstreams, run continuously:',
    'pillars' => array(
        array('title' => 'Strategy & calendar', 'text' => 'Content pillars and a planned month — never a scramble.', 'points' => array(
            'Pillars mapped to your sales cycle: educate, prove, humanize, convert',
            'Monthly calendar with platform-specific formats and posting times',
            'Campaign moments — launches, offers, events — planned in advance',
            'Competitor and trend watch folded into every planning cycle',
        )),
        array('title' => 'Design & video', 'text' => 'Native formats, on-brand, produced at volume.', 'points' => array(
            'Statics, carousels, reels and story sets in your visual system',
            'Hook-first short-form video scripts and edits',
            'Templates that keep output consistent as the team scales',
            'Accessible design: captions, contrast, readable on mobile',
        )),
        array('title' => 'Copy & community', 'text' => 'Words in your voice; conversations handled daily.', 'points' => array(
            'Captions and CTAs written per platform, not pasted across all',
            'Hashtag and SEO strategy tuned to each network’s discovery',
            'Comments, DMs and mentions managed daily with escalation rules',
            'Review and testimonial moments converted into content',
        )),
        array('title' => 'Profiles & reporting', 'text' => 'Optimized storefronts and honest numbers.', 'points' => array(
            'Bio, link-in-bio, highlights and pinned content rebuilt to convert',
            'Platform settings, integrations and tracking connected',
            'Monthly outcome report: reach that mattered, clicks, conversations, leads',
            'Quarterly strategy review against business goals',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'The first ninety days.',
    'timeline' => array(
        array('when' => 'Week 1–2', 'title' => 'Discovery & audit',    'text' => 'Brand, audience and competitor teardown; profiles audited; content history analyzed for what ever worked.'),
        array('when' => 'Week 2–3', 'title' => 'Strategy & pillars',   'text' => 'Content pillars, platform priorities, tone guide and visual direction agreed. Profile optimization shipped.'),
        array('when' => 'Week 3–4', 'title' => 'First calendar',       'text' => 'Month-one calendar produced and approved; template library built; scheduling and engagement workflows wired.'),
        array('when' => 'Month 2',  'title' => 'Operate',              'text' => 'Publishing and community management running on cadence; first video batch produced; A/B testing on hooks and formats begins.'),
        array('when' => 'Month 3',  'title' => 'Compound',             'text' => 'Winning formats doubled down; underperformers retired; first outcome report reviewed against business goals.'),
        array('when' => 'Ongoing',  'title' => 'Review & evolve',      'text' => 'Monthly reports and quarterly strategy reviews keep the presence tied to revenue, not trends.'),
    ),
    'faqTitle' => 'Social media, straight answers.',
    'faq' => array(
        array('q' => 'How often should we post?', 'a' => 'Often enough to stay visible, deliberately enough to stay good. Posting more while ignoring what performs is actively wasteful — we set a sustainable cadence per platform, then let performance data decide where frequency pays and where it doesn’t.'),
        array('q' => 'Will this actually produce leads?', 'a' => 'Organic social is a trust and discovery channel: it makes paid campaigns convert better and gives sales warm context. Direct leads come through the paths we build into content — link-in-bio offers, DM automations, lead magnets — and we track each one rather than guessing.'),
        array('q' => 'Which platforms should we be on?', 'a' => 'The ones where your buyers spend attention — not every logo on the internet. We recommend a focused set (usually two or three) and go deep there, rather than thin everywhere.'),
        array('q' => 'Do you handle video and design, or do we supply assets?', 'a' => 'We produce design and short-form video in-house as part of the service, using your brand assets plus our own production. If you have existing footage or product shots, we fold them in; if not, we build the visual system from scratch.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint', 'title' => 'The Social Media Growth Blueprint', 'note' => 'Pillars, cadence and formats, free', 'url' => url('resources/social-media-growth-blueprint')),
        array('label' => 'Playbook',  'title' => 'The Lead Follow-Up Swipe File',     'note' => 'Turn social conversations into leads', 'url' => url('resources/lead-follow-up-swipe-file')),
        array('label' => 'Service',   'title' => 'Graphic Design',                    'note' => 'Brand system behind the feed', 'url' => url('services/graphic-design')),
    ),
    'deliverables' => array(
        'Content strategy and monthly editorial calendar',
        'Platform-native design and short-form video production',
        'Copywriting in your brand voice for every post',
        'Posting and scheduling across your chosen platforms',
        'Daily community management — comments, DMs, mentions',
        'Profile optimization: bio, links, highlights, pinned posts',
        'Monthly outcome reporting and quarterly strategy reviews',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('graphic-design', 'meta-ads', 'seo'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

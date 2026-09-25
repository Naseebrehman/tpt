<?php
/**
 * The Pie Technologies — Social Media Management service page
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'social-media-management',
    'title' => 'Your Brand. Every Feed. Every Day.',
    'lead'  => 'Done-for-you social media: strategy, design, captions, scheduling and community management — so your brand shows up consistently everywhere, without you touching a thing.',
    'seoTitle' => 'Social Media Management Agency | Content That Builds Brands',
    'seoDesc'  => 'Full-service social media management: content calendars, graphic design, caption writing, hashtag research, scheduling, community management and monthly reports.',
    'intro'   => array(
        'heading'    => 'What\'s included',
        'title'      => 'A full social department, without the payroll.',
        'paragraphs' => array(
            'Posting randomly is why most brands see nothing from social. We run your channels like a media company: a planned calendar, on-brand design, captions written to stop the scroll, and a publishing rhythm your audience can set a watch by.',
            'Behind the scenes, a strategist, a designer, a copywriter and a community manager work your account every week — and at the end of every month you get a report that explains what grew, what flopped and what we\'re doing next.',
        ),
        'features' => array(
            array('icon' => 'calendar', 'title' => 'Content Calendar',      'text' => 'A month-ahead plan mapped to your goals, seasons and launches — approved by you before anything goes live.'),
            array('icon' => 'pen',      'title' => 'Graphic Design',        'text' => 'On-brand statics, carousels and Reels covers designed in-house. No recycled Canva templates with your logo slapped on.'),
            array('icon' => 'edit',     'title' => 'Caption Writing',       'text' => 'Hooks, stories and CTAs written in your brand voice — captions people actually read to the last line.'),
            array('icon' => 'search',   'title' => 'Hashtag Research',      'text' => 'Sized and rotated hashtag sets that put you in front of buyers, not bots.'),
            array('icon' => 'clock',    'title' => 'Scheduling',            'text' => 'Every post queued at the exact time your audience is online, across every platform, every time zone.'),
            array('icon' => 'users',    'title' => 'Community Management',  'text' => 'Comments answered, DMs triaged, reviews flagged. Your audience gets a reply, not silence.'),
            array('icon' => 'chart',    'title' => 'Monthly Reports',       'text' => 'Growth, reach, engagement and best-performer analysis in plain language — with next month\'s plan attached.'),
        ),
    ),
    'platformsTitle' => 'Every platform your audience lives on.',
    'platforms' => array(
        array('icon' => 'instagram', 'label' => 'Instagram'),
        array('icon' => 'facebook',  'label' => 'Facebook'),
        array('icon' => 'tiktok',    'label' => 'TikTok'),
        array('icon' => 'linkedin',  'label' => 'LinkedIn'),
        array('icon' => 'twitter',   'label' => 'Twitter / X'),
        array('icon' => 'youtube',   'label' => 'YouTube'),
        array('icon' => 'pin',       'label' => 'Pinterest'),
    ),
    'pillarsTitle' => 'The content strategy, in three pillars.',
    'pillars' => array(
        array(
            'title'  => 'Attract',
            'text'   => 'Reach content engineered for the algorithm: Reels, trends and shareable formats that put your brand in front of people who have never heard of you.',
            'points' => array('Reels & short-form video', 'Trend-jacking with brand fit', 'Shareable carousel hooks'),
        ),
        array(
            'title'  => 'Engage',
            'text'   => 'Community content that turns followers into fans: stories, polls, behind-the-scenes and conversations that make your brand feel human.',
            'points' => array('Daily story sequences', 'Comment & DM management', 'UGC amplification'),
        ),
        array(
            'title'  => 'Convert',
            'text'   => 'Revenue content that moves people off the feed: offers, proof, product education and CTAs timed to buying moments.',
            'points' => array('Offer & launch posts', 'Social proof & reviews', 'Click-through landing content'),
        ),
    ),
    'mock'  => true,
    'steps' => array(
        array('title' => 'Audit',      'text' => 'We dissect your current channels, competitors and audience to find the whitespace.'),
        array('title' => 'Strategy',   'text' => 'Pillars, voice, visual direction and a KPI per platform. Approved by you.'),
        array('title' => 'Production', 'text' => 'Design, copy and video produced in monthly batches — always two weeks ahead.'),
        array('title' => 'Publish',    'text' => 'Scheduled at optimal times across all platforms, formatted natively per channel.'),
        array('title' => 'Engage',     'text' => 'Community management every business day: replies, DMs, reviews, flags.'),
        array('title' => 'Report',     'text' => 'Monthly growth report plus a strategy call to steer next month\'s calendar.'),
    ),
    'stats' => array(
        array('value' => 3.8, 'decimals' => 1, 'suffix' => '×', 'label' => 'Average follower growth in 6 months'),
        array('value' => 6,   'suffix' => '',  'label' => 'Platforms managed under one roof'),
        array('value' => 7,   'suffix' => '×', 'label' => 'Publishing days per week — always on'),
    ),
    'testimonial' => true,
    'faq' => array(
        array('q' => 'How many posts per week do you publish?', 'a' => 'It depends on the platform and your goals, but a typical managed account runs 4–5 feed posts and daily stories per platform, plus 2–3 Reels. The calendar is agreed with you monthly, so cadence always matches your capacity to fulfil demand.'),
        array('q' => 'Do you create the visuals or do we send them?', 'a' => 'We create them. Our in-house designers produce statics, carousels and short-form video edits from your brand assets and any raw photos or clips you share. If you have a photographer, even better — we\'ll give them a shot list.'),
        array('q' => 'Will we approve content before it goes live?', 'a' => 'Yes. You get the full monthly calendar in one approval round — captions, designs and schedule. Anything you flag gets revised before publishing. Emergency posts can be fast-tracked same-day.'),
        array('q' => 'Can you manage comments and DMs too?', 'a' => 'That\'s the community management layer, included. We reply in your brand voice, escalate sales enquiries to your team instantly, and flag anything sensitive before it becomes a public problem.'),
        array('q' => 'What if our industry is "boring"?', 'a' => 'Boring industries produce some of our best-performing content — because almost nobody in them is trying. Education, process transparency and founder-led storytelling make "boring" genuinely watchable.'),
        array('q' => 'How do you measure success?', 'a' => 'Against the KPIs we set in strategy: reach and follower growth for Attract content, engagement rate for Engage, and clicks, leads or sales attributed to social for Convert. All of it lands in your monthly report.'),
    ),
    'cta' => array(
        'title'  => 'Get a Free Social Media Audit.',
        'text'   => 'We\'ll review your last 90 days of content and show you the three fixes that would move your numbers fastest.',
        'button' => 'Get My Free Audit',
    ),
);

require dirname(__DIR__) . '/includes/service-page.php';

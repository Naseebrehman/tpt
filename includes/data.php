<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — shared site data (nav, services, footer)
 * ---------------------------------------------------------------------------
 *  Static presentation data used by the navbar, home page accordion,
 *  "THE SYSTEM" section, footer and contact form dropdowns. Database-driven
 *  content (blog, portfolio, testimonials, team) lives in MySQL instead.
 * ---------------------------------------------------------------------------
 */

/** The seven service disciplines, in brand order. */
function pieServices()
{
    return array(
        array(
            'key'     => 'meta-ads',
            'num'     => '01',
            'name'    => 'Meta Ads',
            'tagline' => 'Facebook & Instagram advertising engineered for return, not reach.',
            'desc'    => 'Full-funnel paid social across Facebook, Instagram, Messenger and WhatsApp. We build the audiences, write the hooks, ship the creatives and scale what prints money — with your ROAS as the only scoreboard.',
            'tags'    => array('Lead Gen', 'Retargeting', 'Creative Strategy', 'ROAS'),
            'metrics' => array(array('4.2×', 'Average ROAS'), array('68%', 'Lower cost per lead'), array('120+', 'Campaigns launched')),
            'icon'    => 'target',
            'image'   => 'assets/images/work-fashion.jpg',
        ),
        array(
            'key'     => 'social-media-management',
            'num'     => '02',
            'name'    => 'Social Media Management',
            'tagline' => 'Your brand, every feed, every day — without you lifting a finger.',
            'desc'    => 'Content calendars, design, captions, hashtags, scheduling and community management across every platform that matters. A consistent, on-brand presence that compounds month after month.',
            'tags'    => array('Content Calendars', 'Design', 'Community', 'Reporting'),
            'metrics' => array(array('3.8×', 'Follower growth'), array('6', 'Platforms managed'), array('Daily', 'Publishing cadence')),
            'icon'    => 'megaphone',
            'image'   => 'assets/images/blog-social.jpg',
        ),
        array(
            'key'     => 'seo',
            'num'     => '03',
            'name'    => 'SEO',
            'tagline' => 'Rank higher. Get found. Grow faster — on traffic you own.',
            'desc'    => 'Technical fixes, on-page optimisation, authority building and content that answers real search intent. Slow-burn, compounding growth that keeps paying long after ad budgets stop.',
            'tags'    => array('Technical', 'On-Page', 'Link Building', 'Local SEO'),
            'metrics' => array(array('+212%', 'Average organic growth'), array('Top 3', 'For money keywords'), array('6–12', 'Months to compound')),
            'icon'    => 'search',
            'image'   => 'assets/images/why-data.jpg',
        ),
        array(
            'key'     => 'web-development',
            'num'     => '04',
            'name'    => 'Web Development',
            'tagline' => 'Websites that work as hard as you do.',
            'desc'    => 'Fast, secure, conversion-focused websites — landing pages, business sites, e-commerce and web apps. Built to load in under a second and turn traffic into pipeline.',
            'tags'    => array('Landing Pages', 'E-Commerce', 'Web Apps', 'WordPress'),
            'metrics' => array(array('<1s', 'Load time target'), array('98', 'Lighthouse score'), array('100%', 'Mobile responsive')),
            'icon'    => 'code',
            'image'   => 'assets/images/work-saas.jpg',
        ),
        array(
            'key'     => 'email-marketing',
            'num'     => '05',
            'name'    => 'Email Marketing',
            'tagline' => 'The channel you own. The revenue you keep.',
            'desc'    => 'Flows, campaigns and list strategy that turn one-time buyers into repeat revenue. Welcome sequences, cart recovery, win-backs and newsletters people actually open.',
            'tags'    => array('Flows', 'Campaigns', 'Automation', 'Retention'),
            'metrics' => array(array('32%', 'Average open rate'), array('28%', 'Revenue from email'), array('4.1×', 'Flow ROI')),
            'icon'    => 'mail',
            'image'   => 'assets/images/why-team.jpg',
        ),
        array(
            'key'     => 'google-ads',
            'num'     => '06',
            'name'    => 'Google Ads',
            'tagline' => 'Be the answer at the exact moment intent appears.',
            'desc'    => 'Search, Performance Max, Shopping and YouTube campaigns built on tight intent mapping and ruthless negative-keyword discipline. High-intent traffic, measured to the riyal.',
            'tags'    => array('Search', 'Shopping', 'PMax', 'YouTube'),
            'metrics' => array(array('5.6×', 'Average ROAS'), array('-41%', 'Cost per acquisition'), array('24/7', 'Intent capture')),
            'icon'    => 'chart',
            'image'   => 'assets/images/work-cafe.jpg',
        ),
        array(
            'key'     => 'branding-design',
            'num'     => '07',
            'name'    => 'Branding & Design',
            'tagline' => 'Look like the market leader before you are one.',
            'desc'    => 'Identity systems, logos, guidelines and creative direction that make every touchpoint feel intentional. Brand work that makes your paid traffic convert harder.',
            'tags'    => array('Identity', 'Logo', 'Guidelines', 'Creative Direction'),
            'metrics' => array(array('60+', 'Identities built'), array('2 wks', 'Average sprint'), array('100%', 'On-brand delivery')),
            'icon'    => 'pen',
            'image'   => 'assets/images/work-fitness.jpg',
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

/** Main navigation structure. */
function pieNav()
{
    return array(
        array('key' => 'home',      'label' => 'Home',      'url' => url('')),
        array('key' => 'services',  'label' => 'Services',  'url' => url('services/meta-ads'), 'dropdown' => true),
        array('key' => 'portfolio', 'label' => 'Portfolio', 'url' => url('portfolio')),
        array('key' => 'resources', 'label' => 'Resources', 'url' => url('resources')),
        array('key' => 'blog',      'label' => 'Blog',      'url' => url('blog')),
        array('key' => 'about',     'label' => 'About',     'url' => url('about')),
        array('key' => 'contact',   'label' => 'Contact',   'url' => url('contact')),
    );
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
        'facebook'  => '<path d="M14 8h3V4h-3a5 5 0 0 0-5 5v3H6v4h3v8h4v-8h3l1-4h-4V9a1 1 0 0 1 1-1z"/>',
        'linkedin'  => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7"/><path d="M8 7v.01"/><path d="M12 17v-4a3 3 0 0 1 6 0v4"/>',
        'tiktok'    => '<path d="M14 4v10.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4c.5 2.5 2.3 4.2 5 4.5"/>',
        'twitter'   => '<path d="m4 4 7.5 9.5L4.5 20h2l6-5.3L16.8 20H20l-7.8-9.9L19.3 4h-2l-5.4 4.8L8 4H4z"/>',
        'youtube'   => '<rect x="2.5" y="6" width="19" height="12.5" rx="3.5"/><path d="m10.5 9.5 5 2.8-5 2.8v-5.6z"/>',
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'sparkle'   => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3z"/><path d="M19 16l.9 2.1L22 19l-2.1.9L19 22l-.9-2.1L16 19l2.1-.9L19 16z"/>',
        'filter'    => '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5z"/>',
    );
    $body = isset($paths[$name]) ? $paths[$name] : $paths['sparkle'];
    return '<svg class="icon icon-' . esc($name) . '" width="' . (int) $size . '" height="' . (int) $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $body . '</svg>';
}

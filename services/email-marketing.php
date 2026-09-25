<?php
/**
 * The Pie Technologies — Email Marketing service page
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'email-marketing',
    'title' => 'The Channel You Own. The Revenue You Keep.',
    'lead'  => 'Flows, campaigns and list strategy that turn one-time buyers into repeat revenue. Welcome sequences, cart recovery, win-backs and newsletters people actually open.',
    'seoTitle' => 'Email Marketing Agency | Flows & Campaigns That Print Revenue',
    'seoDesc'  => 'Email marketing done for you: welcome flows, abandoned cart recovery, win-back sequences, broadcast campaigns, segmentation, deliverability and reporting.',
    'intro'   => array(
        'heading'    => 'What we do',
        'title'      => 'Email is the only channel nobody can take from you.',
        'paragraphs' => array(
            'Algorithms change, ad costs rise, platforms ban accounts. Your list stays. Done properly, email becomes the highest-ROI line in your marketing P&L — typically 25–40% of total revenue for e-commerce brands.',
            'We build the two engines that make it work: automated flows that sell while you sleep, and campaigns that keep your brand top-of-mind between purchases. All of it measured, tested and reported.',
        ),
        'features' => array(
            array('icon' => 'sparkle', 'title' => 'Welcome Flows',      'text' => 'A first-impression sequence that converts new subscribers into first orders within days, not maybe-someday.'),
            array('icon' => 'zap',     'title' => 'Cart Recovery',      'text' => 'Abandoned-cart and browse-abandon sequences with smart timing and offer escalation that recover lost revenue daily.'),
            array('icon' => 'send',    'title' => 'Campaigns',          'text' => 'Broadcasts, launches and seasonal promos — written, designed and segmented, never blasted to the whole list.'),
            array('icon' => 'users',   'title' => 'Segmentation',       'text' => 'VIPs, at-risk, one-time buyers, window shoppers. Right message to right person, based on real behaviour.'),
            array('icon' => 'shield',  'title' => 'Deliverability',     'text' => 'Authentication (SPF/DKIM/DMARC), list hygiene and warm-up discipline so you land in the inbox, not spam.'),
            array('icon' => 'chart',   'title' => 'Reporting & Testing','text' => 'Subject-line and send-time tests every cycle, with revenue-per-recipient as the number we optimise.'),
        ),
    ),
    'accordion' => array(
        'heading' => 'What we build inside your account.',
        'items'   => array(
            array('title' => 'Automated Flows',  'body' => 'Welcome, post-purchase, cart recovery, browse abandonment, win-back and birthday/anniversary flows — the always-on backbone that typically produces half of all email revenue.'),
            array('title' => 'Broadcast Campaigns', 'body' => 'Launches, promos, educational content and story-driven newsletters on a planned cadence that keeps your list warm without burning it out.'),
            array('title' => 'List Growth',      'body' => 'Pop-ups, landing pages and lead magnets engineered for conversion — with double opt-in and compliance handled correctly from day one.'),
            array('title' => 'Testing Programme', 'body' => 'A rolling calendar of subject-line, layout, offer and send-time experiments. Every test logged, every winner rolled out account-wide.'),
        ),
    ),
    'steps' => array(
        array('title' => 'Audit',    'text' => 'Account, deliverability and revenue audit — what your list is worth today and what it should be worth.'),
        array('title' => 'Setup',    'text' => 'Platform configuration, authentication, segments and tracking wired to your store or CRM.'),
        array('title' => 'Flows',    'text' => 'Core automations designed, written and launched in the first 30 days.'),
        array('title' => 'Campaigns','text' => 'Monthly campaign calendar goes live with segmented sends and A/B tests.'),
        array('title' => 'Optimize', 'text' => 'Quarterly flow refreshes, list hygiene and continuous testing against revenue per recipient.'),
    ),
    'stats' => array(
        array('value' => 32,  'suffix' => '%', 'label' => 'Average open rate across managed lists'),
        array('value' => 28,  'suffix' => '%', 'label' => 'Of client revenue attributed to email'),
        array('value' => 4.1, 'decimals' => 1, 'suffix' => '×', 'label' => 'Average return on flow build investment'),
    ),
    'testimonial' => true,
    'faq' => array(
        array('q' => 'Which platform do you work on?', 'a' => 'Klaviyo, Mailchimp, Brevo, ActiveCampaign and ConvertKit are our daily drivers. If you\'re on something else we\'ll tell you honestly whether migrating is worth it — usually it is only when flows and segmentation are being held back.'),
        array('q' => 'How many emails per month is too many?', 'a' => 'Frequency is not the risk; irrelevance is. A well-segmented list tolerates 8–12 touches a month across flows and campaigns. We watch unsubscribe and complaint rates weekly and dial cadence per segment, not per gut feel.'),
        array('q' => 'Our emails go to spam. Can you fix that?', 'a' => 'Almost always. The usual culprits are missing authentication, stale lists, spam-triggering content or a poor sending reputation. We fix the technical layer first, clean the list second, then rebuild engagement with a re-permission campaign.'),
        array('q' => 'We have a tiny list. Is email still worth it?', 'a' => 'Especially then. Flows convert new subscribers from day one, so email revenue scales with your traffic automatically. Building the machine early means every future visitor is worth more.'),
        array('q' => 'Do you write the emails too?', 'a' => 'Yes — strategy, copy and design are all in-house. You approve templates and the monthly calendar; we handle everything between approval and send.'),
        array('q' => 'How do you report results?', 'a' => 'A monthly scorecard: revenue from flows vs campaigns, open/click/conversion rates, list growth and revenue per recipient — plus the test log and next month\'s plan.'),
    ),
    'cta' => array(
        'title'  => 'Get a Free Email Marketing Audit.',
        'text'   => 'We\'ll review your account and show you the flows you\'re missing and the revenue they\'d recover.',
        'button' => 'Get My Free Audit',
    ),
);

require dirname(__DIR__) . '/includes/service-page.php';

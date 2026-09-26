<?php
/**
 * The Pie Technologies — Data Analytics service page (MEASURE)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'data-analytics',
    'title' => 'Data Analytics',
    'lead'  => 'Know what worked. Fund what compounds. Stop arguing from opinions.',
    'seoTitle' => 'Data Analytics — Tracking, Dashboards & Attribution | TPT',
    'seoDesc'  => 'Analytics implementation, KPI frameworks, dashboards, conversion tracking, attribution modeling and A/B testing — turning scattered platform numbers into one decision-grade view.',
    'heroDesc' => 'Every channel reports success while nobody can trace a customer end to end. We install the measurement layer under your marketing: clean tracking, one attribution model, dashboards your team actually opens, and experiments that settle arguments with data.',
    'bullets'  => array(
        'Tracking audited and rebuilt to count real outcomes',
        'One KPI framework the whole company uses',
        'Dashboards that answer “what do we do Monday?”',
        'A/B testing program with proper significance rules',
    ),
    'cta' => array(
        'title'  => 'Making decisions on numbers you can’t trust?',
        'text'   => 'Get a tracking health audit: what your analytics actually record, where the gaps and duplicates are, and what your real cost per lead looks like once the data is honest.',
        'button' => 'Get a Tracking Audit',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'Five dashboards. Zero agreement.',
        'paragraphs' => array(
            'Meta says 40 leads. Google says 34. The CRM says 21. The sales team says half were spam. Every number is “right” inside its own silo — and together they make decision-making impossible. Budget arguments become seniority contests.',
            'The fix is a measurement layer: events that count business outcomes, deduplicated and attributed with one agreed model, surfaced in dashboards built for decisions. When everyone reads the same numbers, the conversation finally becomes about what to do.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Each platform reports a different number of leads',
            'A dashboard nobody has opened since it was built',
            'Budget allocated by whoever presented last',
            'Experiments run without baselines or significance rules',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From raw events to decisions.',
        'lead'    => 'A governed pipeline — garbage is filtered out at every stage.',
        'steps'   => array(
            array('title' => 'Instrument', 'text' => 'Events defined around business outcomes — lead, call, booking, purchase — and fired reliably, server-side where it matters.'),
            array('title' => 'Clean',      'text' => 'Deduplication, spam filtering and naming conventions. One contact, one journey, one record.'),
            array('title' => 'Attribute',  'text' => 'One agreed model connecting outcomes to the touches that produced them — documented, not vibes.'),
            array('title' => 'Visualize',  'text' => 'Dashboards per audience: executives get trends, marketers get levers, sales gets pipeline truth.'),
            array('title' => 'Experiment', 'text' => 'A/B tests with baselines, hypotheses and significance rules — winners ship, losers die.'),
            array('title' => 'Decide',     'text' => 'Monthly reviews where budget moves on evidence. That’s the whole point of the other five stages.'),
        ),
        'note' => 'We treat measurement as infrastructure: documented, versioned, and owned by you — not a black box only we can read.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The analytics operating stack.',
    'pillarsLead'    => 'Four workstreams, run continuously:',
    'pillars' => array(
        array('title' => 'Tracking & data', 'text' => 'The foundation everything trusts.', 'points' => array(
            'GA4 and platform pixel audits, rebuilt around real events',
            'Server-side tracking where privacy and accuracy demand it',
            'CRM and platform data integration into one view',
            'Naming conventions and data hygiene documentation',
        )),
        array('title' => 'KPIs & dashboards', 'text' => 'Numbers with jobs, views with audiences.', 'points' => array(
            'KPI framework tied to business outcomes, agreed company-wide',
            'Executive, marketing and sales dashboards — same truth, different depth',
            'Alerts on the movements that need a human, not a weekly scroll',
            'Looker Studio, custom or in-tool — fit to your stack',
        )),
        array('title' => 'Attribution & reporting', 'text' => 'Whose lead is it, actually?', 'points' => array(
            'Attribution model selected, documented and defended with data',
            'Cost per qualified outcome by channel, campaign and creative',
            'Monthly decision reviews, not just data deliveries',
            'Offline conversion import where sales close outside the browser',
        )),
        array('title' => 'Experimentation', 'text' => 'Settle it with evidence.', 'points' => array(
            'A/B testing program: hypotheses, baselines, significance rules',
            'Landing page, creative and offer experiments prioritized by impact',
            'Test documentation so learning survives staff changes',
            'Predictive views where enough data finally exists',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'The first ninety days.',
    'timeline' => array(
        array('when' => 'Week 1–2', 'title' => 'Audit',        'text' => 'Every tracking layer tested against reality: events, duplicates, gaps, spam. The honest state of your data, documented.'),
        array('when' => 'Week 3–4', 'title' => 'Rebuild',      'text' => 'Event schema defined around outcomes; tracking reimplemented and verified; naming conventions published.'),
        array('when' => 'Week 5–6', 'title' => 'Framework',    'text' => 'KPI tree agreed with leadership; attribution model selected and documented; baselines recorded.'),
        array('when' => 'Week 7–8', 'title' => 'Dashboards',   'text' => 'Role-based dashboards shipped; alerts wired; team trained to read and act, not just look.'),
        array('when' => 'Month 3',  'title' => 'Experiment',   'text' => 'First A/B cycle run to significance; monthly decision review cadence established.'),
        array('when' => 'Ongoing',  'title' => 'Govern',       'text' => 'Quarterly tracking health checks, model reviews and test roadmap refreshes as the business moves.'),
    ),
    'faqTitle' => 'Data analytics, straight answers.',
    'faq' => array(
        array('q' => 'We already have GA4 and platform dashboards. What changes?', 'a' => 'Three things: honesty (tracking audited against real outcomes, duplicates and spam removed), agreement (one KPI framework and attribution model everyone uses), and action (dashboards structured around decisions, with monthly reviews that move budget). The tools often stay; the system around them is what we build.'),
        array('q' => 'How much data do we need before analytics is worth it?', 'a' => 'Less than you think for tracking and dashboards — those pay off from day one by making spend accountable. Statistical luxuries like confident A/B tests and predictive views need volume; we tell you honestly which analyses your data can and cannot support yet, and what will change that.'),
        array('q' => 'Which attribution model should we use?', 'a' => 'The simplest one your data can defend. First-touch credits discovery, last-touch credits conversion, and data-driven models need volume. We select based on your sales cycle and data maturity, document the choice, and revisit it as data accumulates — the worst model is an undocumented one.'),
        array('q' => 'Do dashboards replace our monthly marketing meetings?', 'a' => 'They replace the first hour of them — the part where everyone argues about whose numbers are right. With one agreed view, meetings start at the decision layer: what to fund, what to kill, what to test next. That’s the point.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint', 'title' => 'The Lead Generation Blueprint',        'note' => 'Where measurement meets pipeline', 'url' => url('resources/lead-generation-blueprint')),
        array('label' => 'Blueprint', 'title' => 'The Website Conversion Blueprint',     'note' => 'Instrumenting the conversion layer', 'url' => url('resources/website-conversion-blueprint')),
        array('label' => 'Blueprint', 'title' => 'The Google Ads Blueprint',             'note' => 'Clean data feeding Smart Bidding', 'url' => url('resources/google-ads-blueprint')),
    ),
    'deliverables' => array(
        'Tracking audit and event schema rebuilt around outcomes',
        'GA4, pixel and server-side tracking implementation',
        'KPI framework agreed across the company',
        'Role-based dashboards with alerting',
        'Attribution model selection and documentation',
        'CRM and platform data integration',
        'A/B testing program with monthly decision reviews',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('digital-marketing', 'meta-ads', 'web-development'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

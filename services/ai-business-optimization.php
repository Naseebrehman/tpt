<?php
/**
 * The Pie Technologies — AI Business Optimization service page (GET FOUND)
 */
require_once dirname(__DIR__) . '/includes/init.php';

$service = array(
    'key'   => 'ai-business-optimization',
    'title' => 'AI Business Optimization',
    'lead'  => 'Put AI to work in your business — and make your business legible to AI.',
    'seoTitle' => 'AI Business Optimization — Automation, Agents & AI Visibility | TPT',
    'seoDesc'  => 'AI readiness audits, workflow automation, custom AI agents, content systems and AI-search visibility — implemented and measured against real business outcomes.',
    'heroDesc' => 'Two jobs, one program: use AI inside your business to remove manual drag — agents, automations, analytics — and get found by AI outside it, as customers increasingly ask assistants instead of search engines. We implement both, measure both, and train your team on both.',
    'bullets'  => array(
        'AI readiness audit of your actual workflows',
        'Automations that remove manual drag this quarter',
        'Custom agents grounded in your real business data',
        'Visibility in Google AI Overviews and assistant answers',
    ),
    'cta' => array(
        'title'  => 'Using AI tools — or actually running on AI?',
        'text'   => 'Get an AI readiness assessment: where AI earns its keep in your operations, what it would cost to implement, and how you’d measure it — with the honest “not yet” list included.',
        'button' => 'Get an AI Readiness Assessment',
    ),
    'problem' => array(
        'eyebrow'    => 'The problem',
        'title'      => 'AI pilots everywhere. Systems nowhere.',
        'paragraphs' => array(
            'Most businesses are in the worst of both worlds: staff using random AI tools with no governance, while the actual operations — intake, follow-up, reporting, support — still run on manual drag. Meanwhile, customers start asking AI assistants who to hire, and the assistants answer with whoever they can read, trust and cite. Usually that isn’t you.',
            'The fix isn’t buying another subscription. It’s a program: audit the workflows, automate the ones AI genuinely improves, ground agents in your own data, and make your digital presence structured enough that AI engines cite you — then measure all of it.',
        ),
        'familiarTitle' => 'Sound familiar?',
        'familiar' => array(
            'Team members using five different AI tools, none connected to anything',
            'Hours lost to intake, follow-up and reporting that a system should handle',
            'Chatbots that hallucinate answers instead of your real services',
            'Competitors appearing in AI answers while you’re invisible',
        ),
    ),
    'machine' => array(
        'eyebrow' => 'The machine',
        'title'   => 'From manual drag to measured automation.',
        'lead'    => 'A governed path — every stage earns the next.',
        'steps'   => array(
            array('title' => 'Audit',     'text' => 'Every workflow scored for AI fit: time cost, error rate, data availability, risk. The honest “not yet” list matters as much as the wins.'),
            array('title' => 'Design',    'text' => 'Target-state workflows with humans kept where judgment is required. Agents grounded in your documents, services and policies.'),
            array('title' => 'Automate',  'text' => 'The unglamorous 80% first: routing, follow-up, reporting, data entry — connected to your actual tools.'),
            array('title' => 'Deploy',    'text' => 'Agents and assistants shipped with guardrails: fallback answers, escalation to humans, no invented facts or prices.'),
            array('title' => 'Appear',    'text' => 'Structured content and schema so AI engines can read, trust and cite your business in their answers.'),
            array('title' => 'Measure',   'text' => 'Hours saved, response times, lead quality, AI-search citations — reviewed monthly against the baseline audit.'),
        ),
        'note' => 'We refuse to deploy AI that guesses. Every agent we ship answers from your real business content and hands off to a human when it doesn’t know.',
    ),
    'pillarsEyebrow' => 'What we actually do',
    'pillarsTitle'   => 'The AI optimization stack.',
    'pillarsLead'    => 'Four workstreams, implemented in order:',
    'pillars' => array(
        array('title' => 'Readiness & automation', 'text' => 'The workflows, fixed first.', 'points' => array(
            'AI readiness audit across intake, sales, ops and support',
            'Workflow automation: routing, follow-up, reporting, data entry',
            'Tool consolidation with governance and access rules',
            'ROI model per automation — hours saved against cost',
        )),
        array('title' => 'Agents & assistants', 'text' => 'AI grounded in your business, never guessing.', 'points' => array(
            'Custom AI agents trained on your services, docs and policies',
            'Website assistants with human escalation and lead capture',
            'Internal copilots for sales, support and admin teams',
            'Guardrails: no invented pricing, results or claims — ever',
        )),
        array('title' => 'Content & data systems', 'text' => 'Your knowledge, machine-readable.', 'points' => array(
            'AI-assisted content systems with human review baked in',
            'Structured data and entity clarity for machines to cite',
            'Predictive analytics on the data your tools already collect',
            'Documentation pipelines so the system stays current',
        )),
        array('title' => 'AI search visibility', 'text' => 'Get cited when customers ask assistants.', 'points' => array(
            'Optimization for AI Overviews and assistant answers',
            'Question-shaped content that AI engines prefer to cite',
            'Monitoring your appearance across AI surfaces',
            'Reputation signals: reviews, mentions, consistency',
        )),
    ),
    'timelineEyebrow' => 'How it works',
    'timelineTitle'   => 'The first ninety days.',
    'timeline' => array(
        array('when' => 'Week 1–2', 'title' => 'Readiness audit',   'text' => 'Workflows mapped and scored; data and tool inventory; honest “not yet” list alongside the wins.'),
        array('when' => 'Week 3–4', 'title' => 'Design & ROI',      'text' => 'Target-state workflows designed; guardrails and governance agreed; implementation sequenced by payback.'),
        array('when' => 'Month 2',  'title' => 'First automations', 'text' => 'Highest-payback automations deployed: follow-up, routing, reporting. Team trained on each as it ships.'),
        array('when' => 'Month 3',  'title' => 'Agents live',       'text' => 'First grounded agent deployed (website assistant or internal copilot) with monitoring, escalation and lead capture.'),
        array('when' => 'Ongoing',  'title' => 'Measure & expand',  'text' => 'Monthly review against baseline: hours saved, response times, citations. Next automations earn their place on evidence.'),
    ),
    'faqTitle' => 'AI optimization, straight answers.',
    'faq' => array(
        array('q' => 'Will AI replace our team?', 'a' => 'Not when it’s implemented properly. We automate the drag — data entry, first-response, follow-up, reporting — so your people spend their time on judgment and relationships. Every deployment keeps humans in the loop where judgment matters, and your team is trained to supervise what they no longer do by hand.'),
        array('q' => 'What happens when the AI doesn’t know something?', 'a' => 'It says so. Our agents are grounded in your real business content: when a question falls outside what you’ve documented, they answer “I don’t want to guess — you can speak with the team here” and capture the lead. No invented prices, no fabricated results, ever.'),
        array('q' => 'Is our business data safe with AI tools?', 'a' => 'It depends entirely on implementation — which is why governance is part of the service. We specify what data goes where, use providers with enterprise data terms, keep secrets server-side, and document access rules. Random staff pasting customer data into free chatbots is the risk; a governed system is the fix.'),
        array('q' => 'How do we know AI visibility actually works?', 'a' => 'We monitor it: your appearance in AI Overviews and assistant answers for your money queries, tracked monthly alongside traditional rankings. Structured content that answers questions completely gets cited — we measure the citations and the traffic they bring.'),
    ),
    'deeper' => array(
        array('label' => 'Blueprint', 'title' => 'AI Search Visibility Blueprint', 'note' => 'Get cited by AI engines, free', 'url' => url('resources/ai-search-visibility-blueprint')),
        array('label' => 'Blueprint', 'title' => 'The SEO Blueprint',              'note' => 'The organic foundation AI builds on', 'url' => url('resources/seo-blueprint')),
        array('label' => 'Service',   'title' => 'Data Analytics',                 'note' => 'The measurement layer for every automation', 'url' => url('services/data-analytics')),
    ),
    'deliverables' => array(
        'AI readiness audit with scored workflow map',
        'Workflow automation: routing, follow-up, reporting',
        'Custom AI agents grounded in your business content',
        'Website AI assistant with lead capture and human escalation',
        'AI-assisted content systems with review governance',
        'AI search visibility optimization and monitoring',
        'Team training and monthly ROI measurement',
    ),
    'gallery'     => true,
    'testimonial' => true,
    'related'     => array('seo', 'data-analytics', 'web-development'),
);

require_once dirname(__DIR__) . '/includes/service-page.php';

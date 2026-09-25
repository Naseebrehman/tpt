-- ===========================================================================
--  The Pie Technologies — MySQL schema + seed data
--  Import via phpMyAdmin (Hostinger hPanel → Databases → phpMyAdmin).
--  Default admin login: admin@thepietechnologies.com / Admin@123
--  CHANGE THE PASSWORD IMMEDIATELY after first login (Settings → Change Password).
-- ===========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ---------------------------------------------------------------------------
-- Tables
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  last_login DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_lockouts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150),
  ip_address VARCHAR(45),
  attempts INT DEFAULT 0,
  locked_until DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lock_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(30),
  company VARCHAR(150),
  service VARCHAR(100),
  budget VARCHAR(50),
  message TEXT,
  source VARCHAR(100),
  status ENUM('new','in_progress','replied','closed') DEFAULT 'new',
  notes TEXT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sub_status (status),
  KEY idx_sub_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) UNIQUE NOT NULL,
  category_id INT,
  featured_image VARCHAR(255),
  excerpt TEXT,
  content LONGTEXT,
  tags VARCHAR(255) DEFAULT '',
  author VARCHAR(150) DEFAULT 'The Pie Technologies',
  meta_title VARCHAR(255),
  meta_description TEXT,
  reading_time INT DEFAULT 5,
  views INT DEFAULT 0,
  status ENUM('draft','published') DEFAULT 'draft',
  published_at DATETIME,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_post_status (status),
  KEY idx_post_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  post_id INT,
  name VARCHAR(150),
  email VARCHAR(150),
  comment TEXT,
  status ENUM('pending','approved','spam') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_comment_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portfolio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_name VARCHAR(150),
  service_category VARCHAR(100),
  slug VARCHAR(255) UNIQUE NOT NULL,
  thumbnail VARCHAR(255),
  challenge TEXT,
  strategy TEXT,
  results TEXT,
  stats_json TEXT,
  chart_data_json TEXT,
  testimonial TEXT,
  testimonial_author VARCHAR(150),
  display_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  role VARCHAR(150),
  photo VARCHAR(255),
  bio TEXT,
  linkedin VARCHAR(255),
  twitter VARCHAR(255),
  display_order INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS testimonials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  company VARCHAR(150),
  role VARCHAR(150),
  content TEXT,
  rating TINYINT DEFAULT 5,
  photo VARCHAR(255),
  service VARCHAR(100),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255),
  description TEXT,
  cover_image VARCHAR(255),
  file_path VARCHAR(255),
  resource_type ENUM('guide','template','video') DEFAULT 'guide',
  video_url VARCHAR(500),
  category VARCHAR(100),
  download_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) UNIQUE NOT NULL,
  name VARCHAR(150),
  is_active TINYINT(1) DEFAULT 1,
  subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chatbot_leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(100),
  name VARCHAR(150),
  email VARCHAR(150),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_views (
  id INT AUTO_INCREMENT PRIMARY KEY,
  page VARCHAR(255),
  views INT DEFAULT 0,
  view_date DATE,
  UNIQUE KEY uniq_page_date (page, view_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Seed: admin user  (password: Admin@123 — bcrypt hash)
-- ---------------------------------------------------------------------------
INSERT INTO admin_users (username, email, password_hash) VALUES
('admin', 'admin@thepietechnologies.com', '$2y$12$R9h/cIPz0gi.URNNX3kh2O05GzCKIQQSraH95qooKp4d3EpLZ8Zte');

-- ---------------------------------------------------------------------------
-- Seed: blog categories + posts
-- ---------------------------------------------------------------------------
INSERT INTO blog_categories (id, name, slug) VALUES
(1, 'Meta Ads', 'meta-ads'),
(2, 'SEO', 'seo'),
(3, 'Web Development', 'web-development');

INSERT INTO blog_posts (id, title, slug, category_id, featured_image, excerpt, content, tags, author, meta_title, meta_description, reading_time, views, status, published_at) VALUES
(1,
 'The Meta Ads Checklist We Run Before Every Single Launch',
 'meta-ads-pre-launch-checklist',
 1,
 'assets/images/blog-social.jpg',
 'Ninety percent of "Meta Ads doesn''t work" stories are really tracking, structure or creative problems in disguise. Here is the exact 21-point checklist our media buyers run before any campaign spends a single riyal.',
 '<p>Most accounts we audit were not killed by competition — they were killed by preventable setup mistakes. Wrong conversion event, broad match everything, one ad set doing five jobs, creative that was designed for a boardroom instead of a feed. This is the checklist we run before every launch, in order.</p><h2>1. Tracking first, always</h2><ul><li>Pixel fires on every page, verified in Events Manager.</li><li>Conversions API connected with deduplication enabled.</li><li>The optimisation event matches a real business outcome (purchase, lead, booked call).</li><li>UTM conventions agreed so analytics and Ads Manager can be reconciled.</li></ul><p>If any of those four fail, we stop. Bidding algorithms are only as smart as the signal you feed them, and optimising on broken data is how budgets quietly evaporate.</p><h2>2. Structure that can learn</h2><ul><li>One campaign per funnel stage — no hybrids.</li><li>Ad sets sized to reach at least 30–50 conversions per month each.</li><li>Exclusions applied: purchasers, existing leads, employees.</li><li>Placements reviewed, not just left on Advantage+ default blindly.</li></ul><blockquote>An ad set that cannot exit learning phase is not a campaign, it is a donation.</blockquote><h2>3. Creative built to be tested</h2><p>Every launch ships with at least three distinct angles: a problem-first hook, a proof-first hook and an offer-first hook. Each gets two formats (static and Reel) so we learn whether the message or the medium is doing the work. We label everything in a naming convention that survives handovers.</p><h2>4. The boring safeguards</h2><ul><li>Spend caps or cost caps where volatility would hurt.</li><li>Frequency alerts at 3.5 on cold audiences.</li><li>A comment-moderation plan before traffic arrives.</li><li>A written hypothesis per ad set — what we expect, and what result would prove us wrong.</li></ul><p>That last one matters more than it sounds. When every ad set carries a falsifiable hypothesis, optimisation stops being vibe-checking and starts being science. Run this list on your current account and count how many boxes you can tick — the gaps are usually exactly where your money is leaking.</p>',
 'meta ads, ppc, tracking, launch',
 'The Pie Technologies',
 'Meta Ads Pre-Launch Checklist: 21 Points Before You Spend',
 'The exact pre-launch checklist our media buyers use: tracking, structure, creative angles and safeguards that prevent wasted ad spend on Meta.',
 6, 412, 'published', NOW() - INTERVAL 9 DAY),
(2,
 'SEO in 2025: The Only Three Things That Still Move Rankings',
 'seo-2025-what-still-moves-rankings',
 2,
 'assets/images/why-data.jpg',
 'After a decade of algorithm updates, almost everything that "worked" in SEO has died — except three things. Here is where we put client effort now, and what we stopped doing entirely.',
 '<p>Every core update produces the same two reactions: panic sellers offering "AI-proof SEO secrets", and teams quietly doing the same unglamorous work they did last year. We are the second group. Across the accounts we manage, ranking movement still traces back to three levers.</p><h2>1. Intent match, page by page</h2><p>Google is an intent-matching engine wearing a search box. The pages that win are the ones whose structure, depth and format mirror what the searcher actually needs — a comparison, a price, a checklist, a local answer. We audit every money page against the live SERP: if the top ten are all listicles and you are selling a wall of prose, that is your problem, not your backlink profile.</p><h2>2. Entity-level authority</h2><p>Links still matter, but "2,000 blog comments" never did and still does not. What compounds is being cited where your industry actually talks: digital PR, original data, expert commentary, unlinked mentions recovered. Ten relevant placements outperform two hundred random ones, every single time.</p><h2>3. Technical cleanliness at scale</h2><ul><li>Indexation: no orphan pages, no duplicate cannibalising clusters.</li><li>Core Web Vitals in the green on mobile, measured on field data.</li><li>Internal links pointed deliberately at pages you want to rank.</li></ul><blockquote>Technical SEO is not a growth tactic. It is the removal of the brakes.</blockquote><h2>What we stopped doing</h2><p>Exact-match anchor obsession, city-page spam with swapped nouns, word-count targets, and chasing every featured snippet. None of it survived contact with the helpful-content systems, and all of it consumed budget that intent work would have used better.</p><p>If your traffic dropped after an update, the honest first question is not "what did Google change" but "which of these three levers was I never actually pulling?" The audit answers it in a week; recovery starts the day after.</p>',
 'seo, algorithm updates, content',
 'The Pie Technologies',
 'SEO in 2025: The Three Levers That Still Move Rankings',
 'Intent match, entity authority and technical cleanliness: the only three SEO levers that still move rankings after every core update, explained with our client process.',
 5, 388, 'published', NOW() - INTERVAL 5 DAY),
(3,
 'Your Website Has 0.9 Seconds: What Fast Really Means in 2025',
 'website-speed-what-fast-means',
 3,
 'assets/images/work-saas.jpg',
 'Speed is the cheapest conversion optimisation most brands never do. Here is what "fast" actually means, the four things that usually break it, and how we get client sites under one second on mid-range phones.',
 '<p>Ask ten agencies what a fast website is and you will get ten answers, most of them wrong. A 90 Lighthouse score on a desktop in your office is not what your customer experiences on a three-year-old Android in a parking garage. Field speed is the only speed that pays.</p><h2>The number that matters</h2><p>Largest Contentful Paint under 2.5 seconds is Google''s threshold; under 1.5 seconds is where conversion curves visibly bend in our client data. Every extra second of LCP costs roughly 7% of conversions on commercial pages. That is not a UX opinion, it is a revenue line.</p><h2>The four usual suspects</h2><ul><li><strong>Unoptimised hero images.</strong> A 4 MB camera JPEG where a 180 KB WebP would do. Serve modern formats, proper dimensions, lazy-load below the fold.</li><li><strong>Render-blocking everything.</strong> Six stylesheets and four tag managers before first paint. Defer what is not critical; inline what is.</li><li><strong>Page builders with 40 plugins.</strong> Each one queues requests. We rebuild the same design in a custom theme and cut request counts by 70%.</li><li><strong>No caching story.</strong> Full-page caching plus a CDN turns a 3-second PHP render into a 200 ms edge hit for most visitors.</li></ul><blockquote>Performance is a feature your competitors can see but never copy quickly — because it lives in discipline, not in a plugin.</blockquote><h2>How we build fast by default</h2><p>Semantic HTML first, CSS under 40 KB, zero jQuery, fonts subset and preloaded, images in AVIF/WebP with srcset, and a budget: any new script must justify its milliseconds in a written note. Boring? Completely. Effective? Every launch we ship scores 95+ on mobile Lighthouse with room to spare.</p><p>Run your site through PageSpeed Insights on mobile, then look at the field data section — that is your real score. If LCP is over 2.5 seconds, you are paying a daily tax on every ad click you buy. Fixing it is usually a two-week project with a payback measured in days.</p>',
 'performance, core web vitals, web development',
 'The Pie Technologies',
 'Website Speed in 2025: What Fast Really Means (and How to Get There)',
 'LCP, field data and the four things that break website speed. How we get client sites under one second on mid-range phones — and why it lifts conversions ~7% per second.',
 5, 265, 'published', NOW() - INTERVAL 2 DAY);

INSERT INTO blog_comments (post_id, name, email, comment, status) VALUES
(1, 'Sarah Malik', 'sarah@lumenbeauty.co', 'The falsifiable hypothesis point is gold. We started writing one line per ad set last month and our optimisation meetings are half as long and twice as useful.', 'approved'),
(2, 'Danish Iqbal', 'danish@kardeefoods.com', 'Finally an SEO article that doesn''t sell link packages. The intent-match audit took us two days and explained a drop we''d been guessing at for months.', 'approved');

-- ---------------------------------------------------------------------------
-- Seed: portfolio case studies
-- ---------------------------------------------------------------------------
INSERT INTO portfolio (id, client_name, service_category, slug, thumbnail, challenge, strategy, results, stats_json, chart_data_json, testimonial, testimonial_author, display_order, is_active) VALUES
(1,
 'Aurelia Fashion', 'Meta Ads', 'aurelia-fashion-meta-ads', 'assets/images/work-fashion.jpg',
 'A DTC fashion label spending $6k/month on Meta with a 1.4× ROAS and rising CPMs. Every campaign targeted the same broad "women 18–45" audience, retargeting pooled everyone who had ever visited, and creative fatigue hit every 10 days.',
 'We rebuilt the account around margin, not revenue: catalogue campaigns fed by a cleaned product feed, a three-stage retargeting ladder (view → cart → 30-day dormant), and lookalikes seeded from the top 5% of customers by LTV. Creative moved to a weekly test cadence with UGC-style hooks shot on phone.',
 'Ninety days after restructure, blended ROAS moved from 1.4× to 4.1× and cost per acquisition fell 62%. The retargeting ladder alone recovered $18k of abandoned carts in the first quarter, and the account has scaled 3× since without CPA drifting.',
 '[{"value":"4.1×","label":"ROAS in 90 days"},{"value":"-62%","label":"cost per acquisition"},{"value":"$18k","label":"cart revenue recovered"}]',
 '{"labels":["W1","W2","W3","W4","W5","W6","W7","W8","W9","W10","W11","W12"],"datasets":[{"label":"ROAS","data":[1.4,1.6,1.9,2.3,2.6,2.9,3.2,3.4,3.6,3.8,4.0,4.1]}]}',
 'They treated our budget like it was their own money. First agency in four years that showed us numbers we could take to our accountant.',
 'Ayesha R., Founder — Aurelia Fashion', 1, 1),
(2,
 'Brew Theory', 'Social Media', 'brew-theory-social-media', 'assets/images/work-cafe.jpg',
 'A specialty coffee roaster with beautiful product and a dead Instagram: 900 followers, two posts a month, zero foot traffic attributable to social. The founder was posting himself, at midnight, from memory.',
 'We built a three-pillar calendar (brew education, roastery behind-the-scenes, community features) at five posts plus daily stories per week, introduced a recognisable visual template system, and ran a local UGC campaign with five micro-creators within 8 km of the cafe.',
 'Six months later the account sits at 3.7k followers with a 9.4% engagement rate, and the Saturday "brew class" posts sell out within 48 hours of publishing. Social now drives roughly a third of weekend foot traffic according to their till surveys.',
 '[{"value":"+312%","label":"followers in 6 months"},{"value":"9.4%","label":"average engagement rate"},{"value":"48h","label":"to sell out weekly classes"}]',
 '{"labels":["M1","M2","M3","M4","M5","M6"],"datasets":[{"label":"Followers","data":[900,1350,1900,2500,3100,3700]}]}',
 'Our cafe became a place people photograph before they even order. I still don''t fully understand how, but the queue on Saturdays explains it.',
 'Omar T., Owner — Brew Theory', 2, 1),
(3,
 'IronCore Fitness', 'SEO', 'ironcore-fitness-seo', 'assets/images/work-fitness.jpg',
 'A five-location gym chain invisible outside brand search: ranking page three for "gym near me" variants in their own cities, with duplicate location pages and a site that took 6 seconds to load on mobile.',
 'Technical rebuild first (2.1s → 0.9s LCP), then unique local landing pages per location with real class schedules, trainer profiles and schema. A digital-PR push earned placements on three national fitness publications, and review velocity became a weekly KPI per branch.',
 'Organic sessions grew 218% in twelve months. The chain now holds map-pack top three in all five cities and ranks #1 for 14 money keywords including "personal trainer [city]". Trial bookings from organic went from 11 to 63 per month.',
 '[{"value":"+218%","label":"organic sessions in 12 months"},{"value":"#1","label":"for 14 money keywords"},{"value":"6×","label":"trial bookings from organic"}]',
 '{"labels":["M1","M3","M6","M9","M12"],"datasets":[{"label":"Organic sessions / month","data":[820,1150,1780,2300,2610]}]}',
 'We stopped paying for billboards. The phones ring because people found us on Google — every single day.',
 'Marcus D., Operations Director — IronCore Fitness', 3, 1),
(4,
 'Nimbus SaaS', 'Web Development', 'nimbus-saas-web-development', 'assets/images/work-saas.jpg',
 'A B2B SaaS whose WordPress site took 5.8 seconds to load, scored 41 on mobile Lighthouse and converted 0.6% of traffic to demo bookings. Paid traffic was being poured into a leaky bucket.',
 'Full rebuild as a custom lightweight theme: message-matched landing templates per campaign, a three-field demo form, social proof above the fold, and a component library so marketing can ship new pages without developers. Migrated 140 URLs with a full redirect map.',
 'Load time dropped to 0.8 seconds, mobile Lighthouse hit 98, and demo conversion rose to 2.4% — a 4× lift on the same ad spend. The marketing team now ships landing pages same-day using the component library.',
 '[{"value":"0.8s","label":"load time (was 5.8s)"},{"value":"98","label":"mobile Lighthouse score"},{"value":"+300%","label":"demo conversion rate"}]',
 '{"labels":["Before","W2","W4","W8","W12"],"datasets":[{"label":"Demo conversion %","data":[0.6,0.9,1.4,1.9,2.4]}]}',
 'Same traffic, four times the demos. The site finally works as hard as our sales team does.',
 'Lena K., VP Marketing — Nimbus SaaS', 4, 1);

-- ---------------------------------------------------------------------------
-- Seed: testimonials
-- ---------------------------------------------------------------------------
INSERT INTO testimonials (name, company, role, content, rating, photo, service, is_active) VALUES
('Ayesha Rahman', 'Aurelia Fashion', 'Founder', 'We came for Meta Ads and stayed for the honesty. They killed two of our campaigns in week two because the maths didn''t work, then rebuilt the account to 4.1× ROAS. No agency has ever talked to us like that.', 5, '', 'Meta Ads', 1),
('Omar Talal', 'Brew Theory', 'Owner', 'Our socials went from a ghost town to the reason people mention us at the counter. The calendar, the designs, the replies to comments — all handled, all on brand, every single week.', 5, '', 'Social Media', 1),
('Marcus Dean', 'IronCore Fitness', 'Operations Director', 'Twelve months ago we were invisible on Google in our own cities. Today we own the map pack in all five locations. The reporting is so clear our board reads it without me translating.', 5, '', 'SEO', 1),
('Lena Kowalski', 'Nimbus SaaS', 'VP Marketing', 'The rebuild paid for itself in one quarter. Same traffic, four times the demos, and my team ships landing pages without waiting on engineers. Genuinely the smoothest agency project I''ve run.', 5, '', 'Web Development', 1),
('Bilal Ahmed', 'Zayn Estates', 'Managing Director', 'Google Ads was burning $4k a month on clicks that never called us. They rebuilt the structure, fixed the tracking and cut our cost per qualified enquiry by half in six weeks.', 4, '', 'Google Ads', 1);

-- ---------------------------------------------------------------------------
-- Seed: resources (8 guides + 3 templates + 2 videos)
-- ---------------------------------------------------------------------------
INSERT INTO resources (title, description, cover_image, file_path, resource_type, video_url, category, download_count, is_active) VALUES
('The Complete Meta Ads Blueprint 2025', 'The full-funnel framework we use on every account: structure, tracking, creative testing cadence and scaling rules — with the exact campaign map.', 'assets/images/covers/guide-meta-ads-blueprint.svg', 'uploads/resources/meta-ads-blueprint-2025.pdf', 'guide', '', 'Paid Social', 342, 1),
('Social Media Content Strategy Playbook', 'How to build a three-pillar content calendar that attracts, engages and converts — plus 40 hook templates you can steal this week.', 'assets/images/covers/guide-social-playbook.svg', 'uploads/resources/social-content-playbook.pdf', 'guide', '', 'Social Media', 289, 1),
('SEO Audit Checklist & Action Plan', 'The 47-point technical, on-page and authority audit we run before every SEO engagement, sequenced by impact-to-effort ratio.', 'assets/images/covers/guide-seo-audit.svg', 'uploads/resources/seo-audit-checklist.pdf', 'guide', '', 'SEO', 251, 1),
('Website Launch Checklist', 'Every item between "design done" and "live and ranking": redirects, speed budgets, tracking, accessibility and the 24-hour post-launch watch list.', 'assets/images/covers/guide-web-launch.svg', 'uploads/resources/website-launch-checklist.pdf', 'guide', '', 'Web Development', 176, 1),
('Email Marketing Mastery Guide', 'Flows, campaigns and segmentation explained with benchmarks: the seven automations every store needs and the metrics that prove they work.', 'assets/images/covers/guide-email-mastery.svg', 'uploads/resources/email-marketing-mastery.pdf', 'guide', '', 'Email', 143, 1),
('Google Ads Quick Start Guide', 'From account creation to first conversion: intent mapping, match types, negatives and the bidding ladder for budgets under $3k/month.', 'assets/images/covers/guide-google-ads.svg', 'uploads/resources/google-ads-quick-start.pdf', 'guide', '', 'Paid Search', 198, 1),
('Brand Building Blueprint', 'Positioning, personality and visual systems: the workshop framework we use to take a brand from DIY to investor-ready in six weeks.', 'assets/images/covers/guide-brand-blueprint.svg', 'uploads/resources/brand-building-blueprint.pdf', 'guide', '', 'Branding', 121, 1),
('The Digital Marketing ROI Guide', 'How to build one dashboard that tells you the truth: attribution models, blended CAC, and the spreadsheet we give every client on day one.', 'assets/images/covers/guide-roi.svg', 'uploads/resources/marketing-roi-guide.pdf', 'guide', '', 'Strategy', 167, 1),
('30-Day Content Calendar Template', 'A plug-and-play calendar with pillar mapping, format mix and publishing slots for Instagram, LinkedIn and TikTok.', 'assets/images/covers/template-content-calendar.svg', 'uploads/resources/content-calendar-template.pdf', 'template', '', 'Social Media', 94, 1),
('Monthly Reporting Template', 'The exact one-page report format our clients receive: KPIs, wins, losses and next month''s plan — no 40-slide decks.', 'assets/images/covers/template-report.svg', 'uploads/resources/monthly-report-template.pdf', 'template', '', 'Reporting', 77, 1),
('Ad Creative Brief Template', 'The brief our designers receive for every ad: hook, angle, proof, CTA and format — the reason our first-pass approval rate is 90%.', 'assets/images/covers/template-ad-brief.svg', 'uploads/resources/ad-creative-brief-template.pdf', 'template', '', 'Paid Social', 68, 1),
('Meta Ads Account Teardown (Live Audit)', 'We audit a real e-commerce account on camera: what''s leaking, what we''d rebuild first, and the numbers behind each decision.', 'assets/images/covers/video-teardown.svg', '', 'video', 'https://www.youtube.com/watch?v=YOUR_VIDEO_ID_1', 'Paid Social', 0, 1),
('SEO Basics in 12 Minutes', 'The plain-English primer we send every new client: how search works, what we control, and what a realistic timeline looks like.', 'assets/images/covers/video-seo-basics.svg', '', 'video', 'https://www.youtube.com/watch?v=YOUR_VIDEO_ID_2', 'SEO', 0, 1);

-- ---------------------------------------------------------------------------
-- Seed: team
-- ---------------------------------------------------------------------------
INSERT INTO team_members (name, role, photo, bio, linkedin, twitter, display_order, is_active) VALUES
('Ali Raza', 'Founder & Growth Strategist', 'assets/images/team-1.jpg', 'Started The Pie Technologies with one client and one rule: if we can''t measure it, we don''t sell it.', 'https://linkedin.com/in/', '', 1, 1),
('Hina Shahid', 'Head of Paid Media', 'assets/images/team-2.jpg', 'Runs every Meta and Google account in the house. Allergic to vanity metrics since 2018.', 'https://linkedin.com/in/', '', 2, 1),
('Daniyal Khan', 'Lead Developer', 'assets/images/team-3.jpg', 'Builds the sub-second websites our clients'' competitors keep screenshotting.', 'https://linkedin.com/in/', '', 3, 1);

-- ---------------------------------------------------------------------------
-- Seed: sample contact submissions (for dashboard demo)
-- ---------------------------------------------------------------------------
INSERT INTO contact_submissions (name, email, phone, company, service, budget, message, source, status, created_at) VALUES
('Fatima Noor', 'fatima@velastudio.com', '+92 301 2223344', 'Vela Studio', 'Meta Ads', '$1000–$2500', 'We sell handmade jewellery online and our current ads break even at best. Would love an audit and a plan for Q4.', 'Instagram', 'new', NOW() - INTERVAL 3 HOUR),
('James Carter', 'james@cartersdental.co.uk', '+44 20 7946 0011', 'Carters Dental', 'SEO', '$2500–$5000', 'Ranking nowhere for "dentist manchester" despite five years in business. Need local SEO properly done.', 'Google', 'in_progress', NOW() - INTERVAL 2 DAY),
('Mona Ellis', 'mona@lumenbeauty.co', '', 'Lumen Skincare', 'Social Media', '$1000–$2500', 'Looking for a full content calendar and Reels production for a skincare launch in January.', 'Referral', 'replied', NOW() - INTERVAL 6 DAY),
('Ahmed Sultan', 'ahmed@kardeefoods.com', '+971 50 123 4567', 'Kardee Foods', 'Web Development', '$5000+', 'Our Shopify store is slow and checkout drops 60% of carts. Want a rebuild proposal with timelines.', 'LinkedIn', 'new', NOW() - INTERVAL 9 DAY),
('Priya Anand', 'priya@nimbushealth.io', '', 'Nimbus Health', 'Not Sure', "Let's Discuss", 'Early-stage healthtech. Not sure if we need ads, SEO or content first — looking for direction before we spend.', 'Google', 'closed', NOW() - INTERVAL 14 DAY);

-- ---------------------------------------------------------------------------
-- Seed: newsletter + settings
-- ---------------------------------------------------------------------------
INSERT INTO newsletter_subscribers (email, name, is_active) VALUES
('fatima@velastudio.com', 'Fatima Noor', 1),
('mona@lumenbeauty.co', 'Mona Ellis', 1),
('test@subscribed-example.com', 'Test Subscriber', 0);

INSERT INTO settings (setting_key, setting_value) VALUES
('smtp_host', ''),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_user', ''),
('smtp_pass', ''),
('smtp_from_name', 'The Pie Technologies'),
('smtp_from_email', 'hello@thepietechnologies.com'),
('gemini_api_key', ''),
('chatbot_system_prompt', 'You are PIE Bot, the friendly and professional assistant for The Pie Technologies, a full-service digital marketing agency. You help visitors understand our services: Meta Ads (Facebook & Instagram advertising), Social Media Management, SEO, Web Development, Email Marketing, Google Ads, and Branding & Design. Be concise, helpful and professional. When appropriate, encourage visitors to fill out the contact form or book a free consultation. If asked about pricing, say packages are customized per client and suggest they get in touch for a free quote. Always stay on topic about digital marketing and our agency services. If asked something unrelated, politely redirect.'),
('site_name', 'The Pie Technologies'),
('site_tagline', 'A full-service growth agency for brands that mean business. Meta Ads, SEO, social, web and email — engineered around one number: yours.'),
('site_phone', '+92 300 0000000'),
('site_email', 'hello@thepietechnologies.com'),
('site_address', 'Lahore, Pakistan'),
('whatsapp_number', '+92 300 0000000'),
('google_analytics_id', ''),
('facebook_pixel_id', ''),
('meta_title', 'Digital Marketing Agency That Actually Moves Numbers'),
('meta_description', 'The Pie Technologies is a full-service growth agency: Meta Ads, SEO, Social Media Management, Web Development, Email Marketing, Google Ads and Branding & Design for brands that mean business.'),
('og_image', 'assets/images/og-image.jpg'),
('founder_name', 'Ali Raza'),
('instagram_url', 'https://instagram.com/thepietechnologies'),
('facebook_url', 'https://facebook.com/thepietechnologies'),
('linkedin_url', 'https://linkedin.com/company/thepietechnologies'),
('tiktok_url', 'https://tiktok.com/@thepietechnologies'),
('twitter_url', 'https://x.com/thepietechnologies'),
('youtube_url', 'https://youtube.com/@thepietechnologies'),
('maintenance_mode', '0'),
('maintenance_ip', '');

SET FOREIGN_KEY_CHECKS = 1;

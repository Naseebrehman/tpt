<?php
/** Clearly labeled, fictional starter content. No client results are implied. */
$posts = array();
$articles = array(
    array('a-better-campaign-brief', 'Sample: A better campaign brief in five questions', 'Paid Media', 'assets/images/blog-social.jpg',
        'A practical starting point for connecting your offer, audience and measurement before buying traffic.',
        '<h2>Start with the business goal</h2><p>Before choosing an ad format, write down the action you want a customer to take. A useful brief connects that action to the offer, the audience and a measurable next step.</p><h2>Five questions for your team</h2><ol><li>What problem does the offer solve?</li><li>Who needs that solution now?</li><li>What can we honestly prove?</li><li>What happens after someone clicks?</li><li>How will we decide whether the test is working?</li></ol><h2>Make room for learning</h2><p>Document one hypothesis per creative direction. Decide your review schedule and budget boundaries before launch, and keep a record of the changes you make.</p>'),
    array('website-launch-checks', 'Sample: The small checks that make a better website launch', 'Websites', 'assets/images/work-saas.jpg',
        'Forms, accessibility, mobile navigation and analytics deserve the same care as your homepage.',
        '<h2>Test the whole journey</h2><p>Visit your site on a small phone and follow the path from the first page to a completed enquiry. Check that every call to action goes somewhere useful.</p><h2>Before you publish</h2><ul><li>Submit every form and check the notification.</li><li>Use the keyboard to reach menus, buttons and inputs.</li><li>Check text contrast and image descriptions.</li><li>Verify redirects, canonical URLs and the sitemap.</li><li>Confirm that analytics records only the events you need.</li></ul><h2>After launch</h2><p>Keep a rollback plan, monitor errors and schedule an early review with the people who answer customer enquiries.</p>'),
    array('local-search-foundations', 'Sample: Local search starts with useful information', 'Search', 'assets/images/work-cafe.jpg',
        'A simple introduction to service pages, consistent business details and helpful local content.',
        '<h2>Be easy to understand</h2><p>State what you do, who you help and where you work. Clear service descriptions are more useful than repeating location keywords.</p><h2>Build a consistent foundation</h2><p>Keep your contact details, opening hours and service areas consistent across your website and business listings. Use real photographs and answer the questions your customers ask.</p><h2>Measure meaningful actions</h2><p>Track enquiry quality, bookings and phone calls where consent and local rules allow. Rankings alone do not explain whether the website is helping the business.</p>')
);
foreach ($articles as $i => $item) {
    $posts[] = array('id'=>-101-$i, 'title'=>$item[1], 'slug'=>'sample-'.$item[0], 'category_id'=>null,
        'category_name'=>$item[2], 'category_slug'=>strtolower($item[2]) === 'paid media' ? 'paid-media' : strtolower($item[2]),
        'featured_image'=>$item[3], 'excerpt'=>$item[4], 'content'=>'<p><strong>Sample editorial content for demonstration.</strong></p>'.$item[5],
        'tags'=>'sample', 'author'=>'TPT Sample Library', 'meta_title'=>$item[1], 'meta_description'=>$item[4],
        'reading_time'=>3, 'views'=>0, 'status'=>'published', 'published_at'=>'2026-09-01 12:00:00', 'created_at'=>'2026-09-01 12:00:00');
}
$resources = array();
foreach (array(
    array('campaign-planning-template', 'Sample: Campaign planning template', 'template', 'Paid Media', 'assets/images/work-fashion.jpg', 'uploads/resources/ad-creative-brief-template.pdf', 'Map your audience, offer, budget and learning goals before launching a paid campaign.'),
    array('website-launch-checklist', 'Sample: Website launch checklist', 'checklist', 'Websites', 'assets/images/work-saas.jpg', 'uploads/resources/website-launch-checklist.pdf', 'A focused checklist covering mobile layouts, forms, accessibility and launch-day checks.'),
    array('seo-review-checklist', 'Sample: SEO review checklist', 'checklist', 'Search', 'assets/images/work-cafe.jpg', 'uploads/resources/seo-audit-checklist.pdf', 'Review the basics: useful pages, clear titles, working links and consistent business information.')
) as $i=>$item) {
    $resources[] = array('id'=>-201-$i,'title'=>$item[1],'slug'=>'sample-'.$item[0],'description'=>$item[6],
        'content'=>'<p><strong>Sample resource for demonstration.</strong></p><h2>How to use this resource</h2><p>'.$item[6].'</p><p>Download the included starter document, review it with your team and adapt it to your own business. Assign an owner and a review date to each action.</p><h2>Turn the checklist into a plan</h2><p>Separate essential fixes from experiments. Start with the issues that stop a visitor from understanding your offer or completing an enquiry, then measure the effect of your changes.</p>',
        'cover_image'=>$item[4],'file_path'=>$item[5],'resource_type'=>$item[2],'video_url'=>'','category'=>$item[3],
        'reading_time'=>4,'download_count'=>0,'is_active'=>1,'created_at'=>'2026-09-01 12:00:00');
}
$work = array();
foreach (array(
    array('studio-north', 'Sample: Studio North', 'Website Development', 'Fictional professional services', 'assets/images/work-saas.jpg', 'A fictional studio needs a clearer path from portfolio browsing to an enquiry.', 'A concept website with a concise service overview, structured project pages and a short enquiry form.'),
    array('willow-coffee', 'Sample: Willow Coffee', 'Local SEO', 'Fictional hospitality', 'assets/images/work-cafe.jpg', 'A fictional neighbourhood café needs customers to find its location, opening hours and menu.', 'A local-search concept connecting accurate business details, a mobile menu and simple directions.'),
    array('form-and-field', 'Sample: Form & Field', 'Meta Ads', 'Fictional retail', 'assets/images/work-fashion.jpg', 'A fictional independent brand needs a consistent story across its ads and product pages.', 'A concept campaign built around three creative directions, a matching landing page and an agreed measurement plan.')
) as $i=>$item) {
    $work[] = array('id'=>-301-$i,'client_name'=>$item[1],'service_category'=>$item[2],'industry'=>$item[3],
        'slug'=>'sample-'.$item[0],'thumbnail'=>$item[4],'challenge'=>$item[5],'strategy'=>$item[6],
        'results'=>'This is a fictional concept project, not a client engagement. No performance results, revenue figures or testimonials are claimed.',
        'stats_json'=>'[{"value":"Concept","label":"Sample project"}]','chart_data_json'=>'','testimonial'=>'','testimonial_author'=>'',
        'display_order'=>$i,'is_active'=>1,'created_at'=>'2026-09-01 12:00:00');
}
return array('blog_posts'=>$posts,'resources'=>$resources,'portfolio'=>$work);

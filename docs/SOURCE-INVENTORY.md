# Source inventory (pre-upgrade)

## 404.php
26 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/header.php';
## about.php
181 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/header.php';
## admin/actions.php
147 lines; 8 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/email-templates.php';
- require_once dirname(__DIR__) . '/includes/chatbot-api.php';
- requireAdmin();
- function actionJson($ok, $message, $extra = array())
## admin/blog-edit.php
173 lines; 3 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- function cleanAdminHtml($html)
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/blog.php
55 lines; 1 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/comments.php
65 lines; 1 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/export.php
50 lines; 2 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
## admin/index.php
120 lines; 7 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/login.php
92 lines; 6 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
## admin/logout.php
12 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
## admin/password.php
59 lines; 1 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/payments.php
133 lines; 4 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/payments.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/portfolio-edit.php
188 lines; 3 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- function parseStatsLines($text)
- function statsToLines($json)
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/portfolio.php
52 lines; 2 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/resources.php
144 lines; 4 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/settings.php
260 lines; 1 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- function saveSetting($key, $value)
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/submissions.php
217 lines; 3 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
- function selectedIds() {
- function postAction(action, ids) {
## admin/subscribers.php
56 lines; 1 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/team.php
126 lines; 4 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## admin/testimonials.php
133 lines; 4 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- requireAdmin();
- require_once dirname(__DIR__) . '/includes/admin-header.php';
## blog-single.php
169 lines; 2 database calls

- require_once __DIR__ . '/includes/init.php';
- require __DIR__ . '/404.php';
- require_once __DIR__ . '/includes/header.php';
## blog.php
93 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- function blogQueryUrl($params = array())
- require_once __DIR__ . '/includes/header.php';
## chatbot-api.php
12 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/chatbot-api.php';
## contact.php
318 lines; 1 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/email-templates.php';
- function contactJson($ok, $message, $errors = array())
- require_once __DIR__ . '/includes/header.php';
## download.php
43 lines; 2 database calls

- require_once __DIR__ . '/includes/init.php';
## includes/Mailer.php
189 lines; 0 database calls

- class PieMailer
## includes/admin-footer.php
17 lines; 0 database calls

## includes/admin-header.php
71 lines; 0 database calls

- requireAdmin();
## includes/chatbot-api.php
168 lines; 3 database calls

- require_once __DIR__ . '/init.php';
- function chatbotDefaultPrompt()
- function chatbotCallGemini($apiKey, $payload)
- function handleChatbotRequest()
## includes/config.php
73 lines; 0 database calls

## includes/data.php
315 lines; 0 database calls

- function pieDisciplines()
- function pieServices()
- function pieServiceByKey($key)
- function pieServicesByDiscipline($disciplineKey)
- function pieCoreServices()
- function pieServiceRedirects()
- function pieNav()
- function pieIndustries()
- function pieHomeFaq()
- function icon($name, $size = 20)
## includes/db.php
103 lines; 5 database calls

- require_once __DIR__ . '/config.php';
- function dbAll($sql, $params = array())
- function dbOne($sql, $params = array())
- function dbExec($sql, $params = array())
- function dbInsert($sql, $params = array())
## includes/email-templates.php
122 lines; 0 database calls

- function emailShell($innerHtml, $preheader = '')
- function emailAdminNotification($submission)
- function emailClientAutoReply($submission)
- function emailNewsletterWelcome($name, $email)
## includes/footer.php
148 lines; 0 database calls

- require_once __DIR__ . '/init.php';
## includes/functions.php
625 lines; 17 database calls

- require_once __DIR__ . '/db.php';
- function settingsCache()
- function getSetting($key, $default = '')
- function esc($value)
- function e($value)
- function sanitize($input)
- function sanitizeMultiline($input)
- function url($path = '', $pretty = null)
- function asset($path)
- function canonicalUrl($path = '')
- function generateCSRF()
- function csrfField()
- function validateCSRF($token = null)
- function pieClientIp()
- function slugify($text)
- function readingTime($html)
- function formatDate($datetime, $format = 'j M Y')
- function isActive($key, $current = null)
- function jsonCol($value, $default = array())
- function getRecentPosts($limit = 3, $excludeId = null)
- function getPosts($opts = array())
- function getPostBySlug($slug)
- function getPostById($id)
- function getBlogCategories()
- function getApprovedComments($postId)
- function getActiveTestimonials()
- function getPortfolioItems($filter = '')
- function getPortfolioBySlug($slug)
- function getPortfolioById($id)
- function getResources($type = null, $activeOnly = true)
- function getResourceBySlug($slug)
- function getTeamMembers()
- function logPageView($page)
- function isAdminLoggedIn()
- function requireAdmin()
- function currentAdmin()
- function uploadFile($field, $subdir, $allowedExt = array('jpg', 'jpeg', 'png', 'webp', 'svg'), $maxBytes = 5242880)
- function deleteUpload($relativePath)
- function sendEmail($to, $subject, $bodyHtml, $attachments = array())
- require_once __DIR__ . '/Mailer.php';
- require_once VENDOR_PATH . 'autoload.php';
- function csvDownload($filename, $headers, $rows)
- function setFlash($type, $message)
- function getFlash()
## includes/header.php
175 lines; 0 database calls

- require_once __DIR__ . '/init.php';
- function gtag(){dataLayer.push(arguments);}
## includes/init.php
60 lines; 0 database calls

- require_once __DIR__ . '/config.php';
- require_once __DIR__ . '/db.php';
- require_once __DIR__ . '/functions.php';
- require_once __DIR__ . '/data.php';
- require BASE_PATH . '/maintenance.php';
## includes/payments.php
268 lines; 2 database calls

- require_once __DIR__ . '/init.php';
- function piePaymentProviders()
- function piePayRequest($url, $options)
- function pieStripeCheckout($payment)
- function pieStripeVerify($sessionId)
- function piePayPalOrder($payment)
- function piePayPalVerify($orderId)
- function piePaymentByToken($token)
- function piePaymentReconcile($payment)
- function piePaymentNotify($payment)
## includes/service-page.php
559 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/header.php';
## index.php
492 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/header.php';
## maintenance.php
38 lines; 0 database calls

## newsletter.php
73 lines; 4 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/email-templates.php';
## pay-online.php
267 lines; 5 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/payments.php';
- require_once __DIR__ . '/includes/header.php';
## pay-secure.php
143 lines; 3 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/payments.php';
- require __DIR__ . '/404.php';
- require_once __DIR__ . '/includes/header.php';
## portfolio/case-study.php
169 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require dirname(__DIR__) . '/404.php';
- require_once dirname(__DIR__) . '/includes/header.php';
## portfolio.php
80 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/header.php';
## privacy-policy.php
51 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/header.php';
## resource-single.php
120 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require __DIR__ . '/404.php';
- require_once __DIR__ . '/includes/header.php';
## resources.php
152 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/header.php';
## services/ai-business-optimization.php
118 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/app-development.php
119 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/branding-design.php
14 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
## services/data-analytics.php
119 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/digital-marketing.php
118 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/email-marketing.php
14 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
## services/google-ads.php
119 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/graphic-design.php
119 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/local-seo.php
118 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/meta-ads.php
121 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/seo.php
119 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/social-media-management.php
119 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services/web-development.php
119 lines; 0 database calls

- require_once dirname(__DIR__) . '/includes/init.php';
- require_once dirname(__DIR__) . '/includes/service-page.php';
## services-index.php
80 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/header.php';
## sitemap.php
57 lines; 3 database calls

- require_once __DIR__ . '/includes/init.php';
- function sitemapAdd(&$urls, $path, $priority, $changefreq, $lastmod = null)
## terms.php
50 lines; 0 database calls

- require_once __DIR__ . '/includes/init.php';
- require_once __DIR__ . '/includes/header.php';
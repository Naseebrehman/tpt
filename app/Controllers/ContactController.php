<?php
class ContactController
{
    private static function respond($ok, $message, $errors = array())
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array('success' => $ok, 'message' => $message, 'errors' => $errors));
        exit;
    }

    public static function handle($serviceOptions, $budgetOptions = array(), $sourceOptions = array())
    {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
        $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        if (!validateCSRF()) {
            if ($isAjax) { self::respond(false, 'Your session expired. Please refresh the page and try again.'); }
            setFlash('err', 'Your session expired. Please try again.');
            header('Location: ' . url('contact'));
            exit;
        }

        $isProjectPopup = isset($_POST['contact_variant'])
            && is_string($_POST['contact_variant'])
            && $_POST['contact_variant'] === 'project_popup';
        $name    = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
        $email   = sanitize(isset($_POST['email']) ? $_POST['email'] : '');
        $phone   = sanitize(isset($_POST['phone_e164']) ? $_POST['phone_e164'] : (isset($_POST['phone']) ? $_POST['phone'] : ''));
        $company = sanitize(isset($_POST['company']) ? $_POST['company'] : '');
        $service = sanitize(isset($_POST['service']) ? $_POST['service'] : '');
        $message = sanitizeMultiline(isset($_POST['message']) ? $_POST['message'] : '');
        /* Kept for API/back-compat only — the form no longer asks these. */
        $budget  = sanitize(isset($_POST['budget']) ? $_POST['budget'] : '');
        $source  = $isProjectPopup ? 'project_popup' : sanitize(isset($_POST['source']) ? $_POST['source'] : '');

        $errors = array();
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150)                 { $errors['name'] = 'Please enter your full name.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) { $errors['email'] = 'Please enter a valid email address.'; }
        $phoneDigits = preg_replace('/[^0-9]/', '', $phone);
        if ($phoneDigits === '' || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15 || !preg_match('/^\+?[0-9 ()\-]{6,25}$/D', $phone)) {
            $errors['phone'] = 'Please enter a valid phone number (with country code).';
        }
        if ((!$isProjectPopup && !in_array($service, $serviceOptions, true))
            || ($isProjectPopup && $service !== '' && !in_array($service, $serviceOptions, true))) {
            $errors['service'] = 'Please choose a service from the list.';
        }
        if ($budget !== '' && $budgetOptions && !in_array($budget, $budgetOptions, true)) { $errors['budget'] = 'Please pick a budget range from the list.'; }
        if (!$isProjectPopup && $source !== '' && $sourceOptions && !in_array($source, $sourceOptions, true)) { $errors['source'] = 'Please pick an option from the list.'; }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 10000)        { $errors['message'] = 'Please use between 10 and 10,000 characters.'; }
        if (mb_strlen($company) > 150)                                      { $errors['company'] = 'Company name is too long.'; }

        /* simple honeypot: bots fill hidden fields */
        $honeypot = isset($_POST['website_url']) ? trim((string) $_POST['website_url']) : '';
        if ($honeypot !== '') {
            if ($isAjax) { self::respond(true, 'Thanks! We\'ll be in touch within 24 hours.'); }
            header('Location: ' . url('contact'));
            exit;
        }

        /* CAPTCHA — verified server-side (Task 20/22). */
        if (class_exists('Captcha') && !Captcha::verify(Captcha::tokenFromRequest(), pieClientIp())) {
            $errors['captcha'] = 'Please complete the security check and try again.';
        }

        if ($errors) {
            $first = reset($errors);
            if ($isAjax) { self::respond(false, $first, $errors); }
            setFlash('err', $first);
            header('Location: ' . url('contact'));
            exit;
        }

        /* Duplicate-submission guard: identical recent submissions are acknowledged once. */
        $dupe = dbOne(
            'SELECT id FROM contact_submissions WHERE email = ? AND message = ? AND created_at > (NOW() - INTERVAL 5 MINUTE) LIMIT 1',
            array($email, $message)
        );
        if ($dupe) {
            if ($isAjax) { self::respond(true, 'Thanks! Your message is already with our strategy team — a senior strategist replies within one business day.'); }
            setFlash('ok', 'Thanks! Your message has already been received.');
            header('Location: ' . url('contact') . '?sent=1');
            exit;
        }

        $submission = array(
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'company'    => $company,
            'service'    => $service,
            'budget'     => $budget,
            'message'    => $message,
            'source'     => $source,
            'status'     => 'new',
            'notes'      => '',
            'ip_address' => pieClientIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : '',
            'created_at' => date('Y-m-d H:i:s'),
        );

        $newId = Repository::createContact($submission);

        if ($newId < 0) {
            if ($isAjax) { self::respond(false, 'We couldn\'t save your message. Please email us directly at ' . getSetting('site_email', ADMIN_EMAIL) . '.'); }
            setFlash('err', 'Something went wrong saving your message — please email us directly.');
            header('Location: ' . url('contact'));
            exit;
        }

        /* Centralised notifications (Tasks 6–9) — never block the visitor if mail is misconfigured. */
        try {
            require_once BASE_PATH . '/core/Notifications.php';
            require_once BASE_PATH . '/includes/email-templates.php';
            $adminRows = EmailTemplates::detailTable(emailContactRows($submission));
            $notified = Notifications::notifyAdmins('contact', 'contact_admin', array(
                'name'    => $name,
                'email'   => $email,
                'phone'   => $phone,
                'service' => $service,
                'message' => nl2br(esc($message)),
            ), array(
                'table'     => $adminRows,
                'admin_url' => rtrim(SITE_URL, '/') . '/admin/submissions.php',
            ));
            dbExec('UPDATE contact_submissions SET notification_status = ? WHERE id = ?', array($notified ? 'sent' : 'failed', $newId));
            Notifications::sendTemplate('contact_confirm', $email, array(
                'name'    => $name,
                'email'   => $email,
                'phone'   => $phone,
                'service' => $service,
                'message' => nl2br(esc($message)),
            ), array('table' => $adminRows));
        } catch (Throwable $mailError) {
            error_log('[TPT] Contact mail error: ' . $mailError->getMessage());
        }

        if ($isAjax) {
            self::respond(true, 'Thanks ' . explode(' ', $name)[0] . '! Your message is with our strategy team — a senior strategist replies within one business day.');
        }
        setFlash('ok', 'Thanks! Your message has been received — a senior strategist replies within one business day.');
        header('Location: ' . url('contact') . '?sent=1');
        exit;
    }

    }
}

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

    public static function handle($serviceOptions, $budgetOptions, $sourceOptions)
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

        $name    = sanitize(isset($_POST['name']) ? $_POST['name'] : '');
        $email   = sanitize(isset($_POST['email']) ? $_POST['email'] : '');
        $phone   = sanitize(isset($_POST['phone']) ? $_POST['phone'] : '');
        $company = sanitize(isset($_POST['company']) ? $_POST['company'] : '');
        $service = sanitize(isset($_POST['service']) ? $_POST['service'] : '');
        $budget  = sanitize(isset($_POST['budget']) ? $_POST['budget'] : '');
        $message = sanitizeMultiline(isset($_POST['message']) ? $_POST['message'] : '');
        $source  = sanitize(isset($_POST['source']) ? $_POST['source'] : '');

        $errors = array();
        if (mb_strlen($name) < 2 || mb_strlen($name) > 150)                 { $errors['name'] = 'Please enter your full name.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))                     { $errors['email'] = 'Please enter a valid email address.'; }
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{6,30}$/', $phone))  { $errors['phone'] = 'That phone number doesn\'t look right.'; }
        if ($service !== '' && !in_array($service, $serviceOptions, true))  { $errors['service'] = 'Please pick a service from the list.'; }
        if ($budget !== '' && !in_array($budget, $budgetOptions, true))     { $errors['budget'] = 'Please pick a budget range from the list.'; }
        if ($source !== '' && !in_array($source, $sourceOptions, true))     { $errors['source'] = 'Please pick an option from the list.'; }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 10000)                                       { $errors['message'] = 'Please use between 10 and 10,000 characters.'; }

        if (mb_strlen($company) > 150 || mb_strlen($email) > 150) { $errors['email'] = 'Email or company is too long.'; }

        /* simple honeypot: bots fill hidden fields */
        $honeypot = isset($_POST['website_url']) ? trim((string) $_POST['website_url']) : '';
        if ($honeypot !== '') {
            if ($isAjax) { self::respond(true, 'Thanks! We\'ll be in touch within 24 hours.'); }
            header('Location: ' . url('contact'));
            exit;
        }

        if ($errors) {
            $first = reset($errors);
            if ($isAjax) { self::respond(false, $first, $errors); }
            setFlash('err', $first);
            header('Location: ' . url('contact'));
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

        /* Notifications (never block the visitor if mail is misconfigured) */
        try {
            $notified = sendEmail(getSetting('site_email', ADMIN_EMAIL), 'New Contact Form Submission — ' . $name, emailAdminNotification($submission));
            dbExec('UPDATE contact_submissions SET notification_status = ? WHERE id = ?', array($notified ? 'sent' : 'failed', $newId));
            sendEmail($email, 'We received your message, ' . $name, emailClientAutoReply($submission));
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

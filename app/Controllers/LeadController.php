<?php
class LeadController
{
    public static function show()
    {
        requireAdmin();
        $adminPage = 'leads'; $adminTitle = 'Alia Leads';
        $statuses = array('new', 'in_progress', 'replied', 'closed');
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!validateCSRF()) { http_response_code(403); exit('Security token expired.'); }
            $id = (int) ($_POST['id'] ?? 0);
            if (isset($_POST['delete'])) { $ok = dbExec('DELETE FROM chatbot_leads WHERE id = ?', array($id)); }
            else {
                $status = (string) ($_POST['status'] ?? '');
                if (!in_array($status, $statuses, true)) { http_response_code(422); exit('Invalid status.'); }
                $ok = dbExec('UPDATE chatbot_leads SET status = ?, notes = ? WHERE id = ?', array($status, mb_substr(sanitizeMultiline($_POST['notes'] ?? ''), 0, 10000), $id));
            }
            setFlash($ok >= 0 ? 'ok' : 'err', $ok >= 0 ? 'Lead updated.' : 'Update failed. Run the database migrations.');
            header('Location: ' . url('admin/leads')); exit;
        }
        $q = mb_substr(sanitize($_GET['q'] ?? ''), 0, 150);
        $status = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
        $params = array('%' . $q . '%', '%' . $q . '%');
        $where = '(name LIKE ? OR email LIKE ?)';
        if ($status !== '') { $where .= ' AND status = ?'; $params[] = $status; }
        $page = max(1, (int) ($_GET['page'] ?? 1)); $offset = ($page - 1) * 25;
        if (isset($_GET['export'])) {
            $rows = dbAll('SELECT created_at, name, email, phone, company, service, status, notes FROM chatbot_leads WHERE ' . $where . ' ORDER BY id DESC LIMIT 5000', $params);
            csvDownload('alia-leads.csv', array('Date','Name','Email','Phone','Company','Service','Status','Notes'), $rows); exit;
        }
        $rows = dbAll('SELECT * FROM chatbot_leads WHERE ' . $where . ' ORDER BY id DESC LIMIT 25 OFFSET ' . $offset, $params);
        require BASE_PATH . '/views/admin/leads.php';
    }
}

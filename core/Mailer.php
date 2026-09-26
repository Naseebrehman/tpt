<?php
/**
 * ---------------------------------------------------------------------------
 *  The Pie Technologies — minimal dependency-free SMTP client
 * ---------------------------------------------------------------------------
 *  Used when PHPMailer is not present in /vendor (composer optional).
 *  Supports plain, STARTTLS (tls) and implicit TLS (ssl) with AUTH LOGIN.
 * ---------------------------------------------------------------------------
 */

class PieMailer
{
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $fromName;
    private $fromEmail;
    private $replyTo = '';
    private $socket = null;
    private $lastError = '';

    public function __construct($config = array())
    {
        $this->replyTo = $config['reply_to'] ?? '';
        $this->host       = isset($config['host']) ? $config['host'] : '';
        $this->port       = isset($config['port']) && $config['port'] ? (int) $config['port'] : 587;
        $this->encryption = isset($config['encryption']) ? strtolower($config['encryption']) : 'tls';
        $this->username   = isset($config['username']) ? $config['username'] : '';
        $this->password   = isset($config['password']) ? $config['password'] : '';
        $this->fromName   = isset($config['from_name']) ? $config['from_name'] : 'The Pie Technologies';
        $this->fromEmail  = isset($config['from_email']) ? $config['from_email'] : 'no-reply@thepietechnologies.com';
    }

    /**
     * @param string $to
     * @param string $subject
     * @param string $html
     * @return array array('success'=>bool,'error'=>string)
     */
    public function send($to, $subject, $html)
    {
        foreach (array_merge((array) $to, array($this->fromEmail)) as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) { return array('success' => false, 'error' => 'Invalid sender or recipient email.'); }
        }
        if ($this->replyTo !== '' && !filter_var($this->replyTo, FILTER_VALIDATE_EMAIL)) { return array('success' => false, 'error' => 'Invalid Reply-To email.'); }
        if (preg_match('/[\r\n]/', $subject . $this->fromName)) { return array('success' => false, 'error' => 'Invalid mail header.'); }
        if ($this->host === '') {
            return array('success' => false, 'error' => 'SMTP host is not configured.');
        }

        $remote = $this->encryption === 'ssl' ? 'ssl://' . $this->host : $this->host;
        $this->socket = @stream_socket_client($remote . ':' . $this->port, $errno, $errstr, 15);
        if (!$this->socket) {
            return array('success' => false, 'error' => "Connection failed: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 20);

        if (!$this->expect(array('220'))) {
            return $this->fail('No greeting from server.');
        }
        $this->cmd('EHLO ' . $this->ehloHost());
        if (!$this->expect(array('250'))) {
            return $this->fail('EHLO rejected.');
        }

        if ($this->encryption === 'tls') {
            $this->cmd('STARTTLS');
            if (!$this->expect(array('220'))) {
                return $this->fail('STARTTLS rejected.');
            }
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return $this->fail('TLS negotiation failed.');
            }
            $this->cmd('EHLO ' . $this->ehloHost());
            if (!$this->expect(array('250'))) {
                return $this->fail('EHLO (after TLS) rejected.');
            }
        }

        if ($this->username !== '') {
            $this->cmd('AUTH LOGIN');
            if (!$this->expect(array('334'))) {
                return $this->fail('AUTH not accepted.');
            }
            $this->cmd(base64_encode($this->username));
            if (!$this->expect(array('334'))) {
                return $this->fail('Username rejected.');
            }
            $this->cmd(base64_encode($this->password));
            if (!$this->expect(array('235'))) {
                return $this->fail('Authentication failed — check SMTP username/password.');
            }
        }

        $this->cmd('MAIL FROM:<' . $this->fromEmail . '>');
        if (!$this->expect(array('250'))) {
            return $this->fail('MAIL FROM rejected.');
        }
        foreach ((array) $to as $recipient) {
            $this->cmd('RCPT TO:<' . $recipient . '>');
            if (!$this->expect(array('250', '251'))) {
                return $this->fail('RCPT TO rejected for ' . $recipient);
            }
        }
        $this->cmd('DATA');
        if (!$this->expect(array('354'))) {
            return $this->fail('DATA not accepted.');
        }

        $boundary = 'pie-' . bin2hex(random_bytes(12));
        $plain    = trim(strip_tags(preg_replace('/<(br|\/p|\/div|\/tr|\/h[1-6]|\/li)>/i', "\n", $html)));

        $headers = '';
        $headers .= 'Date: ' . date('r') . "\r\n";
        $headers .= 'From: ' . $this->encodeHeader($this->fromName) . ' <' . $this->fromEmail . '>' . "\r\n";
        if ($this->replyTo !== '') { $headers .= 'Reply-To: <' . $this->replyTo . '>' . "\r\n"; }
        $headers .= 'To: <' . (is_array($to) ? implode('>, <', $to) : $to) . '>' . "\r\n";
        $headers .= 'Subject: ' . $this->encodeHeader($subject) . "\r\n";
        $headers .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $this->ehloHost() . '>' . "\r\n";
        $headers .= 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"' . "\r\n";
        $headers .= "\r\n";
        $headers .= '--' . $boundary . "\r\n";
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        $headers .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
        $headers .= $plain . "\r\n\r\n";
        $headers .= '--' . $boundary . "\r\n";
        $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
        $headers .= $html . "\r\n\r\n";
        $headers .= '--' . $boundary . '--' . "\r\n";
        // Normalize line endings and dot-stuff DATA before the terminator.
        $headers = str_replace(array("\r\n", "\r"), "\n", $headers);
        $headers = preg_replace('/^\./m', '..', $headers);
        $headers = str_replace("\n", "\r\n", $headers) . '.';

        $this->cmd($headers, false);
        if (!$this->expect(array('250'))) {
            return $this->fail('Message body rejected.');
        }

        $this->cmd('QUIT');
        @fclose($this->socket);
        return array('success' => true, 'error' => '');
    }

    /* ------------------------------------------------------------------ */

    private function ehloHost()
    {
        $host = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        return preg_replace('/[^A-Za-z0-9.\-]/', '', $host);
    }

    private function encodeHeader($value)
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function cmd($line, $addCrlf = true)
    {
        fwrite($this->socket, $line . ($addCrlf ? "\r\n" : "\r\n"));
    }

    private function readResponse()
    {
        $data = '';
        while (($line = fgets($this->socket, 512)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
            if (strlen($line) < 4) {
                break;
            }
        }
        return $data;
    }

    private function expect($codes)
    {
        $response = $this->readResponse();
        $this->lastError = trim($response);
        $code = substr($response, 0, 3);
        return in_array($code, $codes, true);
    }

    private function fail($message)
    {
        if ($this->socket) {
            @fclose($this->socket);
        }
        return array('success' => false, 'error' => $message . ' Server said: ' . $this->lastError);
    }
}

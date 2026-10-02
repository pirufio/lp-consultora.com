<?php
/**
 * Contact form endpoint. Receives a POST from the form in index.html and
 * emails it to the address in contact-config.php. Always answers JSON:
 *   { "ok": true }  or  { "ok": false, "error": "invalid|spam|rate|server" }
 * Compatible with PHP 7.4+.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond($status, $body)
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

function fail($status, $error)
{
    respond($status, ['ok' => false, 'error' => $error]);
}

/** UTF-8 safe substring/length that work even if the mbstring extension is disabled. */
function utf8_cut($value, $max)
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return preg_match('/^.{0,' . (int) $max . '}/us', $value, $m) ? $m[0] : '';
}

function utf8_len($value)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }
    return preg_match_all('/./us', $value);
}

/** Single-line, trimmed, length-capped (strips CR/LF/control chars: header-injection safe). */
function clean_line($value, $max)
{
    $value = is_string($value) ? $value : '';
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
    return utf8_cut(trim((string) $value), $max);
}

/** Multi-line text: keeps newlines, drops other control chars. */
function clean_text($value, $max)
{
    $value = is_string($value) ? $value : '';
    $value = str_replace("\r\n", "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value);
    return utf8_cut(trim((string) $value), $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail(405, 'invalid');
}

$configFile = __DIR__ . '/contact-config.php';
if (!is_file($configFile)) {
    error_log('contact.php: contact-config.php is missing');
    fail(500, 'server');
}
$config = require $configFile;

// Only accept submissions coming from our own pages.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    if (!$originHost || !in_array(strtolower($originHost), $config['allowed_hosts'], true)) {
        fail(403, 'invalid');
    }
}

// Spam traps: honeypot must be empty, and humans take a few seconds to fill the form.
if (!empty($_POST['website'])) {
    // Pretend success so bots don't learn anything.
    respond(200, ['ok' => true]);
}
$elapsedMs = isset($_POST['t']) ? (int) $_POST['t'] : 0;
if ($elapsedMs < 2500) {
    fail(400, 'spam');
}

// Fields
$name = clean_line($_POST['name'] ?? '', 100);
$email = clean_line($_POST['email'] ?? '', 150);
$phone = clean_line($_POST['phone'] ?? '', 40);
$topic = clean_line($_POST['topic'] ?? '', 20);
$message = clean_text($_POST['message'] ?? '', 3000);
$lang = (($_POST['lang'] ?? '') === 'en') ? 'en' : 'es';
$consent = !empty($_POST['consent']);

$topics = [
    'company' => 'Empresa que busca talento / Company hiring',
    'candidate' => 'Candidato/a / Candidate',
    'other' => 'Otra consulta / Other',
];

if (
    $name === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !isset($topics[$topic]) ||
    utf8_len($message) < 5 ||
    !$consent
) {
    fail(422, 'invalid');
}

// Rate limit per IP (file based; no database on this site).
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$limit = $config['rate_limit'];
$rateFile = sys_get_temp_dir() . '/lp_contact_' . sha1($ip) . '.json';
$now = time();
$hits = [];
$fh = @fopen($rateFile, 'c+');
if ($fh && flock($fh, LOCK_EX)) {
    $stored = json_decode((string) stream_get_contents($fh), true);
    if (is_array($stored)) {
        foreach ($stored as $ts) {
            if (is_int($ts) && $ts > $now - $limit['window']) {
                $hits[] = $ts;
            }
        }
    }
    if (count($hits) >= $limit['max']) {
        flock($fh, LOCK_UN);
        fclose($fh);
        fail(429, 'rate');
    }
    $hits[] = $now;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($hits));
    flock($fh, LOCK_UN);
}
if ($fh) {
    fclose($fh);
}

/** A send that failed on our side must not use up the visitor's quota. */
function refund_hit($rateFile, $now)
{
    $stored = json_decode((string) @file_get_contents($rateFile), true);
    if (!is_array($stored)) {
        return;
    }
    $i = array_search($now, $stored, true);
    if ($i !== false) {
        unset($stored[$i]);
        @file_put_contents($rateFile, json_encode(array_values($stored)), LOCK_EX);
    }
}

// Compose
$subject = 'Nueva consulta web: ' . $name;
$body = implode("\n", [
    'Nueva consulta desde lp-consultora.com',
    str_repeat('-', 40),
    'Nombre:   ' . $name,
    'Email:    ' . $email,
    'Teléfono: ' . ($phone !== '' ? $phone : '-'),
    'Tipo:     ' . $topics[$topic],
    'Idioma:   ' . $lang,
    str_repeat('-', 40),
    $message,
    str_repeat('-', 40),
    'IP: ' . $ip . '  |  ' . date('Y-m-d H:i:s T'),
]);

// Send: SMTP through PHPMailer when credentials are configured, PHP mail() otherwise.
$smtp = $config['smtp'];
$sent = false;
try {
    if ($smtp['user'] !== '') {
        require __DIR__ . '/lib/PHPMailer/Exception.php';
        require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
        require __DIR__ . '/lib/PHPMailer/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->Port = $smtp['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['user'];
        $mail->Password = $smtp['pass'];
        $mail->SMTPSecure = $smtp['secure'] === 'tls'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;
        if (!empty($config['debug'])) {
            // Log SMTP conversation to error_log, minus client lines that carry credentials.
            error_log('contact.php smtp: user=[' . $smtp['user'] . '] pass_length=' . strlen((string) $smtp['pass']));
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str, $level) {
                $str = trim($str);
                $isClient = strpos($str, 'CLIENT -> SERVER:') === 0;
                $safeClient = preg_match('/^CLIENT -> SERVER: (EHLO|HELO|STARTTLS|AUTH )/', $str);
                if ($isClient && !$safeClient) {
                    return;
                }
                error_log('contact.php smtp: ' . $str);
            };
        }
        $mail->setFrom($config['from'], $config['from_name']);
        $mail->addAddress($config['to']);
        $mail->addReplyTo($email, $name);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(false);
        $sent = $mail->send();
    } else {
        $headers = [
            'From: ' . $config['from_name'] . ' <' . $config['from'] . '>',
            'Reply-To: ' . $email,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $sent = mail(
            $config['to'],
            '=?UTF-8?B?' . base64_encode($subject) . '?=',
            $body,
            implode("\r\n", $headers),
            '-f' . $config['from']
        );
        if (!$sent) {
            error_log('contact.php: mail() returned false (local MTA rejected the message)');
        }
    }
} catch (Exception $e) {
    error_log('contact.php: send failed: ' . $e->getMessage());
}

if (!$sent) {
    refund_hit($rateFile, $now);
    fail(500, 'server');
}
respond(200, ['ok' => true]);

<?php
function smartstock_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function app_url(string $path = ''): string
{
    $config = smartstock_config();
    $baseUrl = rtrim((string)($config['app']['base_url'] ?? 'http://localhost/PRODUCTS_ORDERING'), '/');
    $path = ltrim($path, '/');

    return $path === '' ? $baseUrl : $baseUrl . '/' . $path;
}

function smartstock_send_email(string $to, string $subject, string $textBody, ?string $htmlBody = null): bool
{
    $config = smartstock_config();
    $mailConfig = $config['mail'] ?? [];
    $transport = strtolower((string)($mailConfig['transport'] ?? 'mail'));

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {
        if ($transport === 'smtp') {
            return smartstock_send_smtp($mailConfig, $to, $subject, $textBody, $htmlBody);
        }

        return smartstock_send_php_mail($mailConfig, $to, $subject, $textBody, $htmlBody);
    } catch (Throwable $e) {
        error_log('SmartStock mail failed: ' . $e->getMessage());
        return false;
    }
}

function smartstock_send_php_mail(array $mailConfig, string $to, string $subject, string $textBody, ?string $htmlBody): bool
{
    $fromEmail = (string)($mailConfig['from_email'] ?? 'no-reply@localhost');
    $fromName = (string)($mailConfig['from_name'] ?? 'SmartStock');
    [$headers, $message] = smartstock_mail_message($fromEmail, $fromName, $to, $subject, $textBody, $htmlBody, false);

    return @mail($to, $subject, $message, implode("\r\n", $headers));
}

function smartstock_send_smtp(array $mailConfig, string $to, string $subject, string $textBody, ?string $htmlBody): bool
{
    $host = (string)($mailConfig['host'] ?? '');
    $port = (int)($mailConfig['port'] ?? 587);
    $timeout = (int)($mailConfig['timeout'] ?? 15);
    $encryption = strtolower((string)($mailConfig['encryption'] ?? 'tls'));
    $username = (string)($mailConfig['username'] ?? '');
    $password = (string)($mailConfig['password'] ?? '');
    $fromEmail = (string)($mailConfig['from_email'] ?? $username);
    $fromName = (string)($mailConfig['from_name'] ?? 'SmartStock');

    if ($host === '' || $fromEmail === '') {
        throw new RuntimeException('SMTP host and from email are required.');
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr);
    }

    stream_set_timeout($socket, $timeout);

    try {
        smartstock_smtp_expect($socket, [220]);
        smartstock_smtp_command($socket, 'EHLO ' . smartstock_smtp_domain(), [250]);

        if ($encryption === 'tls') {
            smartstock_smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start SMTP TLS.');
            }
            smartstock_smtp_command($socket, 'EHLO ' . smartstock_smtp_domain(), [250]);
        }

        if ($username !== '') {
            smartstock_smtp_command($socket, 'AUTH LOGIN', [334]);
            smartstock_smtp_command($socket, base64_encode($username), [334]);
            smartstock_smtp_command($socket, base64_encode($password), [235]);
        }

        [$headers, $message] = smartstock_mail_message($fromEmail, $fromName, $to, $subject, $textBody, $htmlBody, true);

        smartstock_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smartstock_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smartstock_smtp_command($socket, 'DATA', [354]);
        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . smartstock_smtp_escape_body($message) . "\r\n.\r\n");
        smartstock_smtp_expect($socket, [250]);
        smartstock_smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }

    return true;
}

function smartstock_mail_message(
    string $fromEmail,
    string $fromName,
    string $to,
    string $subject,
    string $textBody,
    ?string $htmlBody,
    bool $includeToSubject
): array {
    $headers = [
        'From: ' . smartstock_mailbox($fromEmail, $fromName),
        'Reply-To: ' . smartstock_mailbox($fromEmail, $fromName),
        'Date: ' . date(DATE_RFC2822),
        'MIME-Version: 1.0',
    ];

    if ($includeToSubject) {
        array_unshift($headers, 'Subject: ' . smartstock_header_value($subject));
        array_unshift($headers, 'To: ' . $to);
    }

    if ($htmlBody !== null && trim($htmlBody) !== '') {
        $boundary = 'smartstock_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $message = "--$boundary\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
            . $textBody . "\r\n\r\n"
            . "--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
            . $htmlBody . "\r\n\r\n"
            . "--$boundary--";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $message = $textBody;
    }

    return [$headers, $message];
}

function smartstock_mailbox(string $email, string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return $email;
    }

    return '"' . str_replace('"', '\"', $name) . '" <' . $email . '>';
}

function smartstock_header_value(string $value): string
{
    return str_replace(["\r", "\n"], ' ', $value);
}

function smartstock_smtp_domain(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return preg_replace('/:\d+$/', '', $host) ?: 'localhost';
}

function smartstock_smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    return smartstock_smtp_expect($socket, $expectedCodes);
}

function smartstock_smtp_expect($socket, array $expectedCodes): string
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException('SMTP server closed the connection.');
        }
        $response .= $line;
        $code = (int)substr($line, 0, 3);
        $more = isset($line[3]) && $line[3] === '-';
    } while ($more);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
    }

    return $response;
}

function smartstock_smtp_escape_body(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
        if (isset($line[0]) && $line[0] === '.') {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}
?>

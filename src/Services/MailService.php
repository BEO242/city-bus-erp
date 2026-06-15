<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Logger;
use CityBus\Core\Setting;

/**
 * Client SMTP minimal (RFC 5321 + AUTH LOGIN + STARTTLS), sans dépendance externe.
 *
 * Lit la configuration depuis app_settings (catégorie mail.*) :
 *   mail.smtp_host, mail.smtp_port, mail.smtp_encryption (none|tls|ssl),
 *   mail.smtp_username, mail.smtp_password, mail.from_email, mail.from_name.
 *
 * Si mail.smtp_host est vide → log warning et retourne false (pas d'exception).
 * Encodage : sujet et nom d'expéditeur en UTF-8 via "MIME B encoding" ;
 * corps en UTF-8 brut avec Content-Type: text/plain ou text/html selon $isHtml.
 */
final class MailService
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    /** @var resource|null */
    private $socket = null;

    public function __construct()
    {
        $this->host       = trim(Setting::getString('mail.smtp_host', ''));
        $this->port       = max(1, Setting::getInt('mail.smtp_port', 587));
        $this->encryption = strtolower(Setting::getString('mail.smtp_encryption', 'tls'));
        $this->username   = Setting::getString('mail.smtp_username', '');
        $this->password   = Setting::getString('mail.smtp_password', '');
        $this->fromEmail  = Setting::getString('mail.from_email', '');
        $this->fromName   = Setting::getString('mail.from_name', 'CITY BUS');
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->fromEmail !== '';
    }

    /**
     * Envoie un e-mail. Retourne true si succès, false sinon (log écrit).
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = false, ?string $replyTo = null): bool
    {
        if (!$this->isConfigured()) {
            Logger::warning('MailService: SMTP non configuré, e-mail ignoré', ['to' => $to, 'subject' => $subject]);
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Logger::warning('MailService: destinataire invalide', ['to' => $to]);
            return false;
        }

        try {
            $this->connect();
            $this->ehlo();
            if ($this->encryption === 'tls') {
                $this->starttls();
                $this->ehlo();
            }
            if ($this->username !== '') {
                $this->authLogin();
            }
            $this->command("MAIL FROM:<{$this->fromEmail}>", 250);
            $this->command("RCPT TO:<{$to}>", [250, 251]);
            $this->command('DATA', 354);

            $headers = $this->buildHeaders($to, $subject, $isHtml, $replyTo);
            $payload = $headers . "\r\n\r\n" . $this->dotStuff($body) . "\r\n.";
            $this->command($payload, 250);
            $this->command('QUIT', 221);
            $this->disconnect();
            return true;
        } catch (\Throwable $e) {
            Logger::error('MailService: échec envoi SMTP', [
                'to' => $to, 'subject' => $subject, 'error' => $e->getMessage(),
            ]);
            $this->disconnect();
            return false;
        }
    }

    private function connect(): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl://' : '';
        $errno = 0; $errstr = '';
        $sock = @stream_socket_client(
            $scheme . $this->host . ':' . $this->port,
            $errno, $errstr, 10, STREAM_CLIENT_CONNECT
        );
        if (!$sock) {
            throw new \RuntimeException("Connexion SMTP échouée: {$errstr} ({$errno})");
        }
        stream_set_timeout($sock, 15);
        $this->socket = $sock;
        $this->expect(220);
    }

    private function ehlo(): void
    {
        $hostname = gethostname() ?: 'localhost';
        $this->command("EHLO {$hostname}", 250);
    }

    private function starttls(): void
    {
        $this->command('STARTTLS', 220);
        if (!stream_socket_enable_crypto(
            $this->socket, true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
        )) {
            throw new \RuntimeException('STARTTLS: négociation TLS échouée');
        }
    }

    private function authLogin(): void
    {
        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        $this->command(base64_encode($this->password), 235);
    }

    /**
     * @param int|int[] $expected
     */
    private function command(string $cmd, $expected): string
    {
        fwrite($this->socket, $cmd . "\r\n");
        return $this->expect($expected);
    }

    /**
     * @param int|int[] $expected
     */
    private function expect($expected): string
    {
        $expected = (array)$expected;
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 515);
            if ($line === false) break;
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break; // dernière ligne multi-line
        }
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new \RuntimeException('SMTP: code inattendu ' . $code . ' — ' . trim($response));
        }
        return $response;
    }

    private function buildHeaders(string $to, string $subject, bool $isHtml, ?string $replyTo): string
    {
        $contentType = $isHtml ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
        $fromHeader  = $this->encodeHeader($this->fromName) . " <{$this->fromEmail}>";
        $h = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: ' . $to,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType,
            'Content-Transfer-Encoding: 8bit',
            'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . ($this->fromEmailDomain()) . '>',
            'X-Mailer: CityBus/MailService',
        ];
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $h[] = 'Reply-To: ' . $replyTo;
        }
        return implode("\r\n", $h);
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function fromEmailDomain(): string
    {
        $at = strpos($this->fromEmail, '@');
        return $at !== false ? substr($this->fromEmail, $at + 1) : 'localhost';
    }

    /** Échappe les lignes commençant par "." (RFC 5321 §4.5.2). */
    private function dotStuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r", "\n"], "\r\n", $body);
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    private function disconnect(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
    }
}

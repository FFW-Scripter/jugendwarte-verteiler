<?php

declare(strict_types=1);

final class SmtpClient
{
    /** @var list<string> */
    private array $log = [];

    public function __construct(private Config $config)
    {
    }

    /**
     * @param list<string> $rcptEmails
     */
    public function send(string $fromEmail, array $rcptEmails, string $rawMessage): void
    {
        $socket = $this->connect();

        try {
            $this->authenticate($socket);
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>');
            $this->expect($socket, [250]);

            foreach ($rcptEmails as $email) {
                $this->command($socket, 'RCPT TO:<' . $email . '>');
                $this->expect($socket, [250, 251]);
            }

            $this->command($socket, 'DATA');
            $this->expect($socket, [354]);
            $payload = $this->dotStuff($rawMessage);
            if (!str_ends_with($payload, "\r\n")) {
                $payload .= "\r\n";
            }
            fwrite($socket, $payload . ".\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    public function probe(): void
    {
        $socket = $this->connect();

        try {
            $this->authenticate($socket);
            $this->command($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    /** @return list<string> */
    public function transcript(): array
    {
        return $this->log;
    }

    private function remote(): string
    {
        $host = $this->config->smtpHost();
        $port = $this->config->smtpPort();
        if ($host === '') {
            throw new RuntimeException('SMTP-Host ist in der config.php nicht gesetzt.');
        }

        return $this->config->smtpEncryption() === 'ssl'
            ? 'ssl://' . $host . ':' . $port
            : 'tcp://' . $host . ':' . $port;
    }

    /** @return resource */
    private function connect()
    {
        $this->log = [];
        $remote = $this->remote();
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => $this->config->smtpVerifyPeer(),
                'verify_peer_name' => $this->config->smtpVerifyPeer(),
                'allow_self_signed' => !$this->config->smtpVerifyPeer(),
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->config->smtpTimeout(),
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP-Verbindung fehlgeschlagen: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_timeout($socket, $this->config->smtpTimeout());

        try {
            $this->expect($socket, [220]);
            $this->ehlo($socket);

            if ($this->config->smtpEncryption() === 'tls') {
                $this->command($socket, 'STARTTLS');
                $this->expect($socket, [220]);
                $crypto = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
                );
                if ($crypto !== true) {
                    throw new RuntimeException('STARTTLS konnte nicht aktiviert werden.');
                }
                $this->ehlo($socket);
            }

            return $socket;
        } catch (Throwable $e) {
            fclose($socket);
            throw $e;
        }
    }

    /** @param resource $socket */
    private function ehlo($socket): void
    {
        $this->command($socket, 'EHLO ' . $this->config->smtpHelo());
        $this->expect($socket, [250]);
    }

    /** @param resource $socket */
    private function authenticate($socket): void
    {
        $user = $this->config->smtpUsername();
        $pass = $this->config->smtpPassword();
        if ($user === '') {
            return;
        }

        $this->command($socket, 'AUTH LOGIN');
        $this->expect($socket, [334]);
        $this->command($socket, base64_encode($user));
        $this->expect($socket, [334]);
        $this->command($socket, base64_encode($pass), true);
        $this->expect($socket, [235]);
    }

    /**
     * @param resource $socket
     * @param list<int> $accepted
     */
    private function expect($socket, array $accepted): int
    {
        $code = 0;
        while (($line = fgets($socket, 2048)) !== false) {
            $line = rtrim($line, "\r\n");
            $this->log[] = 'S: ' . $this->redact($line);
            if (preg_match('/^(\d{3})([\s-])/', $line, $match) !== 1) {
                continue;
            }
            $code = (int) $match[1];
            if ($match[2] === ' ') {
                break;
            }
        }

        if (!in_array($code, $accepted, true)) {
            throw new RuntimeException(
                'Unerwartete SMTP-Antwort ' . $code . ' (erwartet: ' . implode('/', $accepted) . ').'
            );
        }

        return $code;
    }

    /** @param resource $socket */
    private function command($socket, string $line, bool $secret = false): void
    {
        $this->log[] = 'C: ' . ($secret ? '********' : $this->redact($line));
        fwrite($socket, $line . "\r\n");
    }

    private function redact(string $line): string
    {
        return preg_replace('/(PASS|PASSWORD)[^\r\n]*/i', '$1 ********', $line) ?? $line;
    }

    private function dotStuff(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $normalized = str_replace("\n", "\r\n", $normalized);
        return preg_replace('/^\./m', '..', $normalized) ?? $normalized;
    }
}

<?php

declare(strict_types=1);

final class Config
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data)
    {
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                'config.php fehlt. Bitte config.example.php nach config.php kopieren und anpassen.'
            );
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new RuntimeException('config.php muss ein Array zurückgeben.');
        }

        return new self($data);
    }

    public function appTitle(): string
    {
        return trim((string) ($this->data['app']['title'] ?? 'Jugendwarte-Verteiler'));
    }

    public function appSubtitle(): string
    {
        return trim((string) ($this->data['app']['subtitle'] ?? ''));
    }

    public function appPassword(): string
    {
        return (string) ($this->data['app']['password'] ?? '');
    }

    public function maxAttachmentBytes(): int
    {
        return max(1, (int) ($this->data['app']['max_attachment_bytes'] ?? 12 * 1024 * 1024));
    }

    public function maxAttachments(): int
    {
        return max(1, (int) ($this->data['app']['max_attachments'] ?? 8));
    }

    public function sessionLifetime(): int
    {
        return max(300, (int) ($this->data['app']['session_lifetime'] ?? 28800));
    }

    public function smtpHost(): string
    {
        return trim((string) ($this->data['smtp']['host'] ?? ''));
    }

    public function smtpPort(): int
    {
        $port = (int) ($this->data['smtp']['port'] ?? 587);
        return $port > 0 ? $port : 587;
    }

    public function smtpEncryption(): string
    {
        $value = strtolower(trim((string) ($this->data['smtp']['encryption'] ?? 'tls')));
        return in_array($value, ['tls', 'ssl', 'none'], true) ? $value : 'tls';
    }

    public function smtpUsername(): string
    {
        return (string) ($this->data['smtp']['username'] ?? '');
    }

    public function smtpPassword(): string
    {
        return (string) ($this->data['smtp']['password'] ?? '');
    }

    public function smtpTimeout(): int
    {
        return max(5, (int) ($this->data['smtp']['timeout'] ?? 30));
    }

    public function smtpVerifyPeer(): bool
    {
        return (bool) ($this->data['smtp']['verify_peer'] ?? true);
    }

    public function smtpHelo(): string
    {
        $helo = trim((string) ($this->data['smtp']['helo'] ?? ''));
        if ($helo !== '') {
            return $helo;
        }
        $host = gethostname();
        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    public function smtpDebug(): bool
    {
        return (bool) ($this->data['smtp']['debug'] ?? false);
    }

    public function fromEmail(): string
    {
        return trim((string) ($this->data['mail']['from_email'] ?? ''));
    }

    public function fromName(): string
    {
        return trim((string) ($this->data['mail']['from_name'] ?? ''));
    }

    public function replyTo(): string
    {
        return trim((string) ($this->data['mail']['reply_to'] ?? ''));
    }

    public function toName(): string
    {
        $name = trim((string) ($this->data['mail']['to_name'] ?? 'Jugendwarte'));
        return $name !== '' ? $name : 'Jugendwarte';
    }

    public function toEmail(): string
    {
        $email = trim((string) ($this->data['mail']['to_email'] ?? ''));
        return $email !== '' ? $email : $this->fromEmail();
    }

    /**
     * @return list<array{name: string, email: string}>
     */
    public function legacyRecipients(): array
    {
        $out = [];
        $seen = [];

        foreach ($this->data['recipients'] ?? [] as $row) {
            if (is_string($row)) {
                $email = trim($row);
                $name = '';
            } elseif (is_array($row)) {
                $email = trim((string) ($row['email'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
            } else {
                continue;
            }

            $key = strtolower($email);
            if ($email === '' || isset($seen[$key]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $seen[$key] = true;
            $out[] = ['name' => $name, 'email' => $email];
        }

        return $out;
    }

    public function signatureEnabled(): bool
    {
        return (bool) ($this->data['signature']['enabled'] ?? true);
    }

    public function signatureHtml(): string
    {
        return trim((string) ($this->data['signature']['html'] ?? ''));
    }

    public function isPlaceholderSmtp(): bool
    {
        $host = strtolower($this->smtpHost());
        return $host === '' || str_contains($host, 'example.');
    }
}

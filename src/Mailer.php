<?php

declare(strict_types=1);

final class Mailer
{
    public function __construct(
        private Config $config,
        private MimeMessage $mime,
        private SmtpClient $smtp,
    ) {
    }

    /**
     * @param list<string> $bccEmails
     * @param list<array{path: string, name: string, type: string}> $attachments
     */
    public function send(string $subject, string $htmlBody, array $bccEmails, array $attachments): void
    {
        $from = $this->config->fromEmail();
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Absenderadresse in der config.php ist ungültig.');
        }

        if ($bccEmails === []) {
            throw new RuntimeException('Keine Empfänger ausgewählt.');
        }

        foreach ($bccEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Ungültige Empfängeradresse: ' . $email);
            }
        }

        $raw = $this->mime->build(
            $from,
            $this->config->fromName(),
            $this->config->toEmail(),
            $this->config->toName(),
            $this->config->replyTo(),
            $subject,
            $htmlBody,
            $attachments,
        );

        $this->smtp->send($from, $bccEmails, $raw);
    }
}

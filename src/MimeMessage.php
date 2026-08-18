<?php

declare(strict_types=1);

final class MimeMessage
{
    /**
     * @param list<array{path: string, name: string, type: string}> $attachments
     */
    public function build(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $replyTo,
        string $subject,
        string $htmlBody,
        array $attachments,
    ): string {
        $mixedBoundary = 'mix_' . bin2hex(random_bytes(12));
        $altBoundary = 'alt_' . bin2hex(random_bytes(12));
        $date = date(DATE_RFC2822);
        $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $this->messageIdHost($fromEmail));
        $plain = $this->htmlToPlain($htmlBody);

        $headers = [
            'Date: ' . $date,
            'From: ' . $this->mailbox($fromName, $fromEmail),
            'To: ' . $this->mailbox($toName, $toEmail),
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'X-Mailer: Jugendwarte-Verteiler',
        ];

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $hasAttachments = $attachments !== [];
        $headers[] = $hasAttachments
            ? 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"'
            : 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"';

        $lines = implode("\r\n", $headers) . "\r\n\r\n";

        if ($hasAttachments) {
            $lines .= '--' . $mixedBoundary . "\r\n";
            $lines .= 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . "\r\n\r\n";
            $lines .= $this->alternativeParts($altBoundary, $plain, $htmlBody);
            foreach ($attachments as $file) {
                $lines .= $this->attachmentPart($mixedBoundary, $file);
            }
            $lines .= '--' . $mixedBoundary . "--\r\n";
            return $lines;
        }

        $lines .= $this->alternativeParts($altBoundary, $plain, $htmlBody);
        return $lines;
    }

    private function alternativeParts(string $boundary, string $plain, string $html): string
    {
        $out = '--' . $boundary . "\r\n";
        $out .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $out .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $out .= $this->quotedPrintable($plain) . "\r\n";
        $out .= '--' . $boundary . "\r\n";
        $out .= "Content-Type: text/html; charset=UTF-8\r\n";
        $out .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $out .= $this->base64Body($this->wrapHtml($html));
        $out .= '--' . $boundary . "--\r\n";
        return $out;
    }

    /**
     * @param array{path: string, name: string, type: string} $file
     */
    private function attachmentPart(string $boundary, array $file): string
    {
        $binary = file_get_contents($file['path']);
        if ($binary === false) {
            throw new RuntimeException('Anhang konnte nicht gelesen werden: ' . $file['name']);
        }

        $name = $this->encodeFilename($file['name']);
        $type = $file['type'] !== '' ? $file['type'] : 'application/octet-stream';

        $out = '--' . $boundary . "\r\n";
        $out .= 'Content-Type: ' . $type . '; name="' . $name['ascii'] . '"' . "\r\n";
        $out .= "Content-Transfer-Encoding: base64\r\n";
        $out .= 'Content-Disposition: attachment; filename="' . $name['ascii'] . '"';
        if ($name['utf'] !== '') {
            $out .= ";\r\n filename*=" . $name['utf'];
        }
        $out .= "\r\n\r\n";
        $out .= chunk_split(base64_encode($binary), 76, "\r\n");
        return $out;
    }

    private function mailbox(string $name, string $email): string
    {
        if ($name === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        $value = preg_replace('/[\r\n]+/', ' ', $value) ?? $value;
        if (preg_match('/^[\x20-\x7E]+$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * @return array{ascii: string, utf: string}
     */
    private function encodeFilename(string $name): array
    {
        $name = str_replace(['"', "\r", "\n"], '', $name);
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'anhang';
        if ($ascii === '') {
            $ascii = 'anhang';
        }

        $utf = "UTF-8''" . rawurlencode($name);
        return ['ascii' => $ascii, 'utf' => $utf];
    }

    private function quotedPrintable(string $value): string
    {
        $encoded = quoted_printable_encode($value);
        return str_replace(["\r\n", "\n"], "\r\n", $encoded);
    }

    private function base64Body(string $value): string
    {
        return chunk_split(base64_encode($value), 76, "\r\n");
    }

    private function htmlToPlain(string $html): string
    {
        $text = preg_replace('#<(br|p|/p|div|/div|h2|/h2|h3|/h3|li)[^>]*>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function wrapHtml(string $body): string
    {
        return "<!DOCTYPE html>\r\n"
            . "<html lang=\"de\">\r\n"
            . "<head>\r\n"
            . "<meta charset=\"UTF-8\">\r\n"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\r\n"
            . "<style>\r\n"
            . "body { font-family: Georgia, Times, serif; font-size: 16px;\r\n"
            . "line-height: 1.55; color: #1b1b1b; }\r\n"
            . "</style>\r\n"
            . "</head>\r\n"
            . "<body>\r\n"
            . $body . "\r\n"
            . "</body>\r\n"
            . "</html>\r\n";
    }

    private function messageIdHost(string $email): string
    {
        $at = strrpos($email, '@');
        if ($at === false) {
            return 'localhost';
        }

        return substr($email, $at + 1) ?: 'localhost';
    }
}

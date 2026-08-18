<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$auth->requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$subject = trim((string) ($_POST['subject'] ?? ''));
$bodyRaw = (string) ($_POST['body'] ?? '');
$_SESSION['draft_subject'] = $subject;
$_SESSION['draft_body'] = $bodyRaw;

if (!Csrf::verify($_POST['csrf'] ?? null)) {
    flash_set('error', 'Sitzung ungültig. Bitte die Seite neu laden und erneut senden.');
    header('Location: index.php');
    exit;
}

$sanitizer = new HtmlSanitizer();
$body = $sanitizer->sanitize($bodyRaw);
$plainBody = trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

if ($subject === '') {
    flash_set('error', 'Bitte einen Betreff angeben.');
    header('Location: index.php');
    exit;
}

if ($plainBody === '' && !str_contains($body, '<img')) {
    flash_set('error', 'Bitte eine Nachricht schreiben.');
    header('Location: index.php');
    exit;
}

$allowed = [];
foreach ($recipientStore->all() as $person) {
    $allowed[strtolower($person['email'])] = $person['email'];
}

$selected = [];
foreach ((array) ($_POST['recipients'] ?? []) as $email) {
    $key = strtolower(trim((string) $email));
    if (isset($allowed[$key])) {
        $selected[$key] = $allowed[$key];
    }
}

if ($selected === []) {
    flash_set('error', 'Bitte mindestens einen Empfänger auswählen.');
    header('Location: index.php');
    exit;
}

$html = $body;
if ($config->signatureEnabled() && $config->signatureHtml() !== '') {
    $html .= '<div class="signature">' . $sanitizer->sanitize($config->signatureHtml()) . '</div>';
}

$smtp = new SmtpClient($config);

try {
    $attachments = collect_attachments($config);
    $mailer = new Mailer($config, new MimeMessage(), $smtp);
    $mailer->send($subject, $html, array_values($selected), $attachments);
} catch (Throwable $e) {
    $message = $e->getMessage();
    if ($config->smtpDebug()) {
        error_log('Jugendwarte-Verteiler: ' . $e->getMessage() . "\n" . implode("\n", $smtp->transcript()));
        $message .= ' SMTP-Protokoll steht im PHP-Error-Log.';
    }
    flash_set('error', 'Senden fehlgeschlagen: ' . $message);
    header('Location: index.php');
    exit;
}

unset($_SESSION['draft_subject'], $_SESSION['draft_body']);
$n = count($selected);
flash_set('ok', 'Nachricht wurde an ' . $n . ' Empfänger als BCC gesendet.');
header('Location: index.php');
exit;

/**
 * @return list<array{path: string, name: string, type: string}>
 */
function collect_attachments(Config $config): array
{
    if (!isset($_FILES['attachments']) || !is_array($_FILES['attachments']['name'])) {
        return [];
    }

    $names = $_FILES['attachments']['name'];
    $tmps = $_FILES['attachments']['tmp_name'];
    $errors = $_FILES['attachments']['error'];
    $sizes = $_FILES['attachments']['size'];
    $count = count($names);

    if ($count > $config->maxAttachments()) {
        throw new RuntimeException('Zu viele Anhänge. Maximal ' . $config->maxAttachments() . ' Dateien.');
    }

    $finfo = class_exists(finfo::class) ? new finfo(FILEINFO_MIME_TYPE) : null;
    $out = [];
    $total = 0;

    for ($i = 0; $i < $count; $i++) {
        $error = (int) $errors[$i];
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload fehlgeschlagen (Code ' . $error . ').');
        }

        $tmp = (string) $tmps[$i];
        $name = (string) $names[$i];
        $size = (int) $sizes[$i];
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('Ungültiger Datei-Upload.');
        }

        $total += $size;
        if ($total > $config->maxAttachmentBytes()) {
            throw new RuntimeException('Anhänge sind insgesamt zu groß (max. ' . format_bytes($config->maxAttachmentBytes()) . ').');
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_ATTACHMENT_EXTENSIONS, true)) {
            throw new RuntimeException('Dateityp nicht erlaubt: ' . $name);
        }

        $mime = $finfo?->file($tmp) ?: 'application/octet-stream';
        $officeZip = in_array($ext, OFFICE_ZIP_EXTENSIONS, true) && $mime === 'application/zip';
        if (!$officeZip && !in_array($mime, ALLOWED_ATTACHMENT_TYPES, true)) {
            throw new RuntimeException('Inhaltstyp nicht erlaubt: ' . $name);
        }

        $out[] = [
            'path' => $tmp,
            'name' => $name,
            'type' => $mime,
        ];
    }

    return $out;
}

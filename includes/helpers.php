<?php

declare(strict_types=1);

const ALLOWED_ATTACHMENT_TYPES = [
    'application/pdf',
    'application/zip',
    'application/x-zip-compressed',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/csv',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'text/plain',
    'text/csv',
];

const OFFICE_ZIP_EXTENSIONS = ['docx', 'xlsx', 'odt', 'ods'];

const ALLOWED_ATTACHMENT_EXTENSIONS = [
    'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'csv',
];

const MAX_INLINE_IMAGES = 6;
const MAX_INLINE_IMAGE_BYTES = 512 * 1024;
const MAX_INLINE_IMAGES_TOTAL_BYTES = 2 * 1024 * 1024;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; manifest-src data:; base-uri 'none'; form-action 'self'");
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** @return array{type: string, message: string}|null */
function flash_take(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if (!is_array($flash) || !isset($flash['type'], $flash['message'])) {
        return null;
    }

    return [
        'type' => (string) $flash['type'],
        'message' => (string) $flash['message'],
    ];
}

function format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

function format_datetime(string $iso): string
{
    try {
        $date = new DateTimeImmutable($iso);
        $date = $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $date->format('d.m.Y, H:i') . ' Uhr';
    } catch (Exception) {
        return $iso;
    }
}

function render_head_meta(): void
{
    $manifest = [
        'name' => 'Jugendwarte-Verteiler',
        'short_name' => 'Verteiler',
        'description' => 'Interner Mail-Verteiler für die Jugendwarte',
        'start_url' => 'index.php',
        'display' => 'standalone',
        'background_color' => '#f3ead8',
        'theme_color' => '#9a1f14',
        'icons' => [
            ['src' => 'assets/favicon.svg', 'type' => 'image/svg+xml', 'sizes' => 'any'],
            ['src' => 'assets/icon-192.png', 'type' => 'image/png', 'sizes' => '192x192'],
            ['src' => 'assets/icon-512.png', 'type' => 'image/png', 'sizes' => '512x512'],
        ],
    ];
    $manifestJson = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $manifestDataUri = 'data:application/manifest+json;charset=utf-8,' . rawurlencode($manifestJson);
    ?>
    <meta name="theme-color" content="#9a1f14">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icon-192.png" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="assets/icon-192.png">
    <link rel="manifest" href="<?= $manifestDataUri ?>">
    <?php
}

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Csrf.php';
require_once __DIR__ . '/../src/HtmlSanitizer.php';
require_once __DIR__ . '/../src/MimeMessage.php';
require_once __DIR__ . '/../src/SmtpClient.php';
require_once __DIR__ . '/../src/Mailer.php';
require_once __DIR__ . '/../src/RecipientStore.php';
require_once __DIR__ . '/../src/HistoryStore.php';
require_once __DIR__ . '/topbar.php';

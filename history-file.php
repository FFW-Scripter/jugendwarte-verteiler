<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$auth->requireLogin();

$id = trim((string) ($_GET['id'] ?? ''));
$stored = trim((string) ($_GET['file'] ?? ''));
$entry = $historyStore->get($id);

if ($entry === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Anhang nicht gefunden.';
    exit;
}

$path = $historyStore->attachmentPath($id, $stored);
$meta = null;
foreach ($entry['attachments'] as $file) {
    if ($file['stored'] === $stored) {
        $meta = $file;
        break;
    }
}

if ($path === null || $meta === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Anhang nicht gefunden.';
    exit;
}

$downloadName = str_replace(["\r", "\n", '"'], '', $meta['name']);
header('Content-Type: ' . ($meta['type'] !== '' ? $meta['type'] : 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;

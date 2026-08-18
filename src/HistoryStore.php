<?php

declare(strict_types=1);

final class HistoryStore
{
    public function __construct(private string $root)
    {
    }

    /**
     * @param list<array{name: string, email: string, group: string}> $recipients
     * @param list<array{path: string, name: string, type: string}> $attachments
     */
    public function add(string $subject, string $htmlBody, array $recipients, array $attachments): string
    {
        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $dir = $this->entryDir($id);
        $filesDir = $dir . '/files';

        if (!is_dir($this->root) && !mkdir($this->root, 0775, true) && !is_dir($this->root)) {
            throw new RuntimeException('Historie-Ordner konnte nicht angelegt werden.');
        }
        if (!mkdir($filesDir, 0775, true) && !is_dir($filesDir)) {
            throw new RuntimeException('Historie-Eintrag konnte nicht angelegt werden.');
        }

        $storedFiles = [];
        $usedNames = [];

        foreach ($attachments as $file) {
            $stored = $this->uniqueStoredName((string) $file['name'], $usedNames);
            $usedNames[$stored] = true;
            $target = $filesDir . '/' . $stored;
            if (!@copy($file['path'], $target)) {
                throw new RuntimeException('Anhang konnte nicht in die Historie kopiert werden: ' . $file['name']);
            }

            $storedFiles[] = [
                'name' => (string) $file['name'],
                'stored' => $stored,
                'type' => (string) ($file['type'] ?? 'application/octet-stream'),
                'size' => is_file($target) ? (int) filesize($target) : 0,
            ];
        }

        $meta = [
            'id' => $id,
            'sent_at' => date('c'),
            'subject' => $subject,
            'recipients' => array_values($recipients),
            'attachments' => $storedFiles,
        ];

        $this->writeJson($dir . '/meta.json', $meta);
        if (@file_put_contents($dir . '/body.html', $htmlBody) === false) {
            throw new RuntimeException('Nachricht konnte nicht in die Historie geschrieben werden.');
        }

        $this->prependIndex([
            'id' => $id,
            'sent_at' => $meta['sent_at'],
            'subject' => $subject,
            'recipient_count' => count($recipients),
            'attachment_count' => count($storedFiles),
        ]);

        return $id;
    }

    /**
     * @return list<array{id: string, sent_at: string, subject: string, recipient_count: int, attachment_count: int}>
     */
    public function all(): array
    {
        $indexPath = $this->root . '/index.json';
        if (!is_file($indexPath)) {
            return [];
        }

        $raw = file_get_contents($indexPath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }
            $out[] = [
                'id' => (string) $row['id'],
                'sent_at' => (string) ($row['sent_at'] ?? ''),
                'subject' => (string) ($row['subject'] ?? ''),
                'recipient_count' => (int) ($row['recipient_count'] ?? 0),
                'attachment_count' => (int) ($row['attachment_count'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   id: string,
     *   sent_at: string,
     *   subject: string,
     *   recipients: list<array{name: string, email: string, group: string}>,
     *   attachments: list<array{name: string, stored: string, type: string, size: int}>,
     *   body: string
     * }|null
     */
    public function get(string $id): ?array
    {
        if (!$this->isValidId($id)) {
            return null;
        }

        $metaPath = $this->entryDir($id) . '/meta.json';
        $bodyPath = $this->entryDir($id) . '/body.html';
        if (!is_file($metaPath)) {
            return null;
        }

        $raw = file_get_contents($metaPath);
        if ($raw === false) {
            return null;
        }

        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            return null;
        }

        $recipients = [];
        foreach ($meta['recipients'] ?? [] as $person) {
            if (!is_array($person)) {
                continue;
            }
            $recipients[] = [
                'name' => trim((string) ($person['name'] ?? '')),
                'email' => trim((string) ($person['email'] ?? '')),
                'group' => trim((string) ($person['group'] ?? '')),
            ];
        }

        $attachments = [];
        foreach ($meta['attachments'] ?? [] as $file) {
            if (!is_array($file)) {
                continue;
            }
            $attachments[] = [
                'name' => (string) ($file['name'] ?? ''),
                'stored' => (string) ($file['stored'] ?? ''),
                'type' => (string) ($file['type'] ?? 'application/octet-stream'),
                'size' => (int) ($file['size'] ?? 0),
            ];
        }

        $body = is_file($bodyPath) ? (string) file_get_contents($bodyPath) : '';

        return [
            'id' => (string) ($meta['id'] ?? $id),
            'sent_at' => (string) ($meta['sent_at'] ?? ''),
            'subject' => (string) ($meta['subject'] ?? ''),
            'recipients' => $recipients,
            'attachments' => $attachments,
            'body' => $body,
        ];
    }

    public function attachmentPath(string $id, string $stored): ?string
    {
        if (!$this->isValidId($id) || !$this->isValidStoredName($stored)) {
            return null;
        }

        $path = $this->entryDir($id) . '/files/' . $stored;
        $realRoot = realpath($this->entryDir($id) . '/files');
        $realFile = realpath($path);
        if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return is_file($realFile) ? $realFile : null;
    }

    public function isValidId(string $id): bool
    {
        return preg_match('/^[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$/', $id) === 1;
    }

    private function isValidStoredName(string $name): bool
    {
        return $name !== '' && !str_contains($name, '/') && !str_contains($name, '\\') && $name !== '.' && $name !== '..';
    }

    /**
     * @param array{id: string, sent_at: string, subject: string, recipient_count: int, attachment_count: int} $row
     */
    private function prependIndex(array $row): void
    {
        $list = $this->all();
        array_unshift($list, $row);
        $this->writeJson($this->root . '/index.json', $list);
    }

    private function entryDir(string $id): string
    {
        return $this->root . '/' . $id;
    }

    /**
     * @param array<string, true> $used
     */
    private function uniqueStoredName(string $original, array $used): string
    {
        $base = $this->safeFilename($original);
        $name = $base;
        $n = 2;
        while (isset($used[$name])) {
            $ext = pathinfo($base, PATHINFO_EXTENSION);
            $stem = pathinfo($base, PATHINFO_FILENAME);
            $name = $ext !== '' ? $stem . '-' . $n . '.' . $ext : $stem . '-' . $n;
            $n++;
        }

        return $name;
    }

    private function safeFilename(string $name): string
    {
        $name = str_replace(["\0", '/', '\\'], '', $name);
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            return 'anhang';
        }

        return $name;
    }

    private function writeJson(string $path, mixed $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException(
                'Historie konnte nicht gespeichert werden. Der Webserver braucht Schreibrechte auf '
                . dirname($path) . '.'
            );
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Historie konnte nicht gespeichert werden.');
        }
    }
}

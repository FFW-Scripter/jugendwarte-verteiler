<?php

declare(strict_types=1);

final class RecipientStore
{
    public function __construct(
        private string $path,
        private Config $config,
    ) {
    }

    /**
     * @return list<array{id: string, name: string, email: string, group: string, notes: string}>
     */
    public function all(): array
    {
        $rows = $this->load();
        usort($rows, [$this, 'compareRows']);

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->load() as $row) {
            $label = $this->groupLabel($row['group']);
            $groups[$label] = true;
        }

        $list = array_keys($groups);
        usort($list, [$this, 'compareGroupLabels']);

        return $list;
    }

    /**
     * @return array<string, list<array{id: string, name: string, email: string, group: string, notes: string}>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->all() as $row) {
            $label = $this->groupLabel($row['group']);
            $groups[$label][] = $row;
        }

        uksort($groups, [$this, 'compareGroupLabels']);

        return $groups;
    }

    /**
     * @return array{id: string, name: string, email: string, group: string, notes: string}
     */
    public function add(string $name, string $email, string $group = '', string $notes = ''): array
    {
        $row = $this->normalizeInput($name, $email, $group, $notes);
        $rows = $this->load();

        foreach ($rows as $existing) {
            if (strcasecmp($existing['email'], $row['email']) === 0) {
                throw new RuntimeException('Diese E-Mail-Adresse ist bereits hinterlegt.');
            }
        }

        $row['id'] = $this->newId();
        $rows[] = $row;
        $this->write($rows);

        return $row;
    }

    /**
     * @return array{id: string, name: string, email: string, group: string, notes: string}
     */
    public function update(string $id, string $name, string $email, string $group = '', string $notes = ''): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('Empfänger wurde nicht gefunden.');
        }

        $row = $this->normalizeInput($name, $email, $group, $notes);
        $rows = $this->load();
        $found = false;

        foreach ($rows as $index => $existing) {
            if ($existing['id'] !== $id) {
                if (strcasecmp($existing['email'], $row['email']) === 0) {
                    throw new RuntimeException('Diese E-Mail-Adresse ist bereits hinterlegt.');
                }
                continue;
            }

            $found = true;
            $row['id'] = $id;
            $rows[$index] = $row;
            break;
        }

        if (!$found) {
            throw new RuntimeException('Empfänger wurde nicht gefunden.');
        }

        $this->write($rows);
        return $row;
    }

    public function delete(string $id): void
    {
        $id = trim($id);
        $rows = $this->load();
        $next = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['id'] !== $id
        ));

        if (count($next) === count($rows)) {
            throw new RuntimeException('Empfänger wurde nicht gefunden.');
        }

        $this->write($next);
    }

    public function migrateFromConfigIfMissing(): void
    {
        if (is_file($this->path)) {
            return;
        }

        $legacy = $this->config->legacyRecipients();
        if ($legacy === []) {
            $this->write([]);
            return;
        }

        $rows = [];
        foreach ($legacy as $person) {
            $rows[] = [
                'id' => $this->newId(),
                'name' => $person['name'],
                'email' => $person['email'],
                'group' => '',
                'notes' => '',
            ];
        }

        $this->write($rows);
    }

    /**
     * @param array{id: string, name: string, email: string, group: string, notes: string} $left
     * @param array{id: string, name: string, email: string, group: string, notes: string} $right
     */
    private function compareRows(array $left, array $right): int
    {
        $group = $this->compareGroupLabels(
            $this->groupLabel($left['group']),
            $this->groupLabel($right['group'])
        );
        if ($group !== 0) {
            return $group;
        }

        $leftLabel = $left['name'] !== '' ? $left['name'] : $left['email'];
        $rightLabel = $right['name'] !== '' ? $right['name'] : $right['email'];

        return strcasecmp($leftLabel, $rightLabel);
    }

    private function compareGroupLabels(string $left, string $right): int
    {
        if ($left === $right) {
            return 0;
        }

        if ($left === 'Ohne Gruppe') {
            return 1;
        }
        if ($right === 'Ohne Gruppe') {
            return -1;
        }

        return strcasecmp($left, $right);
    }

    private function groupLabel(string $group): string
    {
        $group = trim($group);
        return $group !== '' ? $group : 'Ohne Gruppe';
    }

    /**
     * @return list<array{id: string, name: string, email: string, group: string, notes: string}>
     */
    private function load(): array
    {
        if (!is_file($this->path)) {
            $this->migrateFromConfigIfMissing();
        }

        $raw = file_get_contents($this->path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Empfängerdatei ist beschädigt.');
        }

        $out = [];
        $seen = [];

        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = $this->normalizeStored($row);
            if ($normalized === null) {
                continue;
            }

            $key = strtolower($normalized['email']);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $normalized;
        }

        return $out;
    }

    /**
     * @param list<array{id: string, name: string, email: string, group: string, notes: string}> $rows
     */
    private function write(array $rows): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Empfängerordner konnte nicht angelegt werden.');
        }

        $this->assertWritable();

        $json = json_encode(
            array_values($rows),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $tmp = $this->path . '.tmp';
        if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException($this->writeErrorMessage($dir));
        }

        if (!@rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new RuntimeException($this->writeErrorMessage($dir));
        }
    }

    private function assertWritable(): void
    {
        $dir = dirname($this->path);

        if (is_writable($dir)) {
            if (!is_file($this->path) || is_writable($this->path)) {
                return;
            }
        }

        throw new RuntimeException($this->writeErrorMessage($dir));
    }

    private function writeErrorMessage(string $dir): string
    {
        return 'Empfänger konnten nicht gespeichert werden. '
            . 'Der Webserver braucht Schreibrechte auf ' . $dir . '. '
            . 'Beispiel: sudo setfacl -m u:www-data:rwx ' . $dir
            . ' && sudo setfacl -d -m u:www-data:rwx ' . $dir;
    }

    /**
     * @return array{id: string, name: string, email: string, group: string, notes: string}|null
     */
    private function normalizeStored(array $row): ?array
    {
        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = $this->newId();
        }

        return [
            'id' => $id,
            'name' => trim((string) ($row['name'] ?? '')),
            'email' => $email,
            'group' => $this->normalizeGroup((string) ($row['group'] ?? '')),
            'notes' => $this->normalizeNotes((string) ($row['notes'] ?? '')),
        ];
    }

    /**
     * @return array{name: string, email: string, group: string, notes: string}
     */
    private function normalizeInput(string $name, string $email, string $group, string $notes): array
    {
        $name = trim($name);
        $email = trim($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Bitte eine gültige E-Mail-Adresse angeben.');
        }

        if (strlen($name) > 120) {
            throw new RuntimeException('Der Name ist zu lang.');
        }

        return [
            'name' => $name,
            'email' => $email,
            'group' => $this->normalizeGroup($group),
            'notes' => $this->normalizeNotes($notes),
        ];
    }

    private function normalizeGroup(string $group): string
    {
        $group = trim(preg_replace('/\s+/u', ' ', $group) ?? $group);
        if ($group === '') {
            return '';
        }

        if (strlen($group) > 80) {
            throw new RuntimeException('Die Gruppe ist zu lang.');
        }

        return $group;
    }

    private function normalizeNotes(string $notes): string
    {
        $notes = trim($notes);
        if (strlen($notes) > 500) {
            throw new RuntimeException('Die Notiz ist zu lang.');
        }

        return $notes;
    }

    private function newId(): string
    {
        return bin2hex(random_bytes(8));
    }
}

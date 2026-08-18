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
     * @return list<array{id: string, name: string, email: string}>
     */
    public function all(): array
    {
        return $this->load();
    }

    /**
     * @return array{id: string, name: string, email: string}
     */
    public function add(string $name, string $email): array
    {
        $row = $this->normalizeInput($name, $email);
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
     * @return array{id: string, name: string, email: string}
     */
    public function update(string $id, string $name, string $email): array
    {
        $id = trim($id);
        if ($id === '') {
            throw new RuntimeException('Empfänger wurde nicht gefunden.');
        }

        $row = $this->normalizeInput($name, $email);
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
            ];
        }

        $this->write($rows);
    }

    /**
     * @return list<array{id: string, name: string, email: string}>
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
     * @param list<array{id: string, name: string, email: string}> $rows
     */
    private function write(array $rows): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Empfängerordner konnte nicht angelegt werden.');
        }

        $json = json_encode(
            array_values($rows),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $tmp = $this->path . '.tmp';
        if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException('Empfänger konnten nicht gespeichert werden.');
        }

        if (!rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new RuntimeException('Empfänger konnten nicht gespeichert werden.');
        }
    }

    /**
     * @return array{id: string, name: string, email: string}|null
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
        ];
    }

    /**
     * @return array{name: string, email: string}
     */
    private function normalizeInput(string $name, string $email): array
    {
        $name = trim($name);
        $email = trim($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Bitte eine gültige E-Mail-Adresse angeben.');
        }

        if (strlen($name) > 120) {
            throw new RuntimeException('Der Name ist zu lang.');
        }

        return ['name' => $name, 'email' => $email];
    }

    private function newId(): string
    {
        return bin2hex(random_bytes(8));
    }
}

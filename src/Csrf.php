<?php

declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verify(?string $token): bool
    {
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!is_string($expected) || $expected === '' || !is_string($token)) {
            return false;
        }

        return hash_equals($expected, $token);
    }
}

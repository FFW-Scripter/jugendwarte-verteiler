<?php

declare(strict_types=1);

final class Auth
{
    public function __construct(private Config $config)
    {
    }

    public function isLoggedIn(): bool
    {
        if (empty($_SESSION['auth_ok'])) {
            return false;
        }

        $loginAt = (int) ($_SESSION['auth_at'] ?? 0);
        if ($loginAt <= 0 || (time() - $loginAt) > $this->config->sessionLifetime()) {
            $this->logout();
            return false;
        }

        return true;
    }

    public function login(string $password): bool
    {
        if (!$this->verifyPassword($password)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['auth_ok'] = true;
        $_SESSION['auth_at'] = time();
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public function requireLogin(): void
    {
        if ($this->isLoggedIn()) {
            return;
        }

        header('Location: login.php');
        exit;
    }

    private function verifyPassword(string $input): bool
    {
        $stored = $this->config->appPassword();
        if ($stored === '' || $stored === 'bitte-aendern') {
            return false;
        }

        if (str_starts_with($stored, '$2y$')
            || str_starts_with($stored, '$2a$')
            || str_starts_with($stored, '$2b$')
            || str_starts_with($stored, '$argon2')
        ) {
            return password_verify($input, $stored);
        }

        return hash_equals($stored, $input);
    }
}

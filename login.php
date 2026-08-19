<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf'] ?? null)) {
        $error = 'Sitzung ungültig. Bitte Seite neu laden.';
    } elseif ($config->appPassword() === '' || $config->appPassword() === 'bitte-aendern') {
        $error = 'Bitte zuerst in der config.php ein eigenes Zugangspasswort setzen.';
    } elseif (!$auth->login((string) ($_POST['password'] ?? ''))) {
        $error = 'Passwort ist nicht korrekt.';
    } else {
        header('Location: index.php');
        exit;
    }
}

$title = $config->appTitle();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anmelden · <?= e($title) ?></title>
    <?php render_head_meta(); ?>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="page-login">
    <main class="login-card">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true"></span>
            <div>
                <p class="eyebrow">Interner Verteiler</p>
                <h1><?= e($title) ?></h1>
            </div>
        </div>
        <p class="lede">Bitte mit dem Zugangspasswort aus der Konfiguration anmelden.</p>
        <?php if ($config->appPassword() === '' || $config->appPassword() === 'bitte-aendern'): ?>
            <p class="notice notice-warn">Vor dem ersten Login in <code>config.php</code> unter <code>app.password</code> ein eigenes Passwort setzen.</p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="notice notice-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post" action="login.php" autocomplete="current-password">
            <input type="hidden" name="csrf" value="<?= e(Csrf::token()) ?>">
            <label for="password">Passwort</label>
            <input id="password" name="password" type="password" required autofocus>
            <button type="submit" class="btn-primary">Anmelden</button>
        </form>
    </main>
</body>
</html>

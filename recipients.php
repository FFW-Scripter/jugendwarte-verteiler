<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$auth->requireLogin();

$flash = flash_take();
$title = $config->appTitle();
$recipients = $recipientStore->all();
$groups = $recipientStore->groups();
$groupedRecipients = $recipientStore->grouped();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf'] ?? null)) {
        flash_set('error', 'Sitzung ungültig. Bitte die Seite neu laden.');
        header('Location: recipients.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'add':
                $recipientStore->add(
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['group'] ?? ''),
                    (string) ($_POST['notes'] ?? ''),
                );
                flash_set('ok', 'Empfänger wurde hinzugefügt.');
                break;

            case 'update':
                $recipientStore->update(
                    (string) ($_POST['id'] ?? ''),
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['group'] ?? ''),
                    (string) ($_POST['notes'] ?? ''),
                );
                flash_set('ok', 'Empfänger wurde aktualisiert.');
                break;

            case 'delete':
                $recipientStore->delete((string) ($_POST['id'] ?? ''));
                flash_set('ok', 'Empfänger wurde entfernt.');
                break;

            default:
                flash_set('error', 'Unbekannte Aktion.');
                break;
        }
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }

    header('Location: recipients.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empfänger verwalten · <?= e($title) ?></title>
    <?php render_head_meta(); ?>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <?php render_topbar('recipients', 'Empfänger verwalten', $title); ?>

    <main class="layout layout-single">
        <?php if ($flash): ?>
            <p class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>

        <section class="panel">
            <h2>Neuen Empfänger hinzufügen</h2>
            <p class="hint">Name ist optional. Gruppen helfen beim Filtern beim Versand. Notizen sind nur intern sichtbar.</p>
            <form class="recipient-form" method="post" action="recipients.php">
                <input type="hidden" name="csrf" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="action" value="add">
                <div class="form-grid">
                    <label>
                        Name
                        <input name="name" type="text" maxlength="120" placeholder="z. B. Max Mustermann">
                    </label>
                    <label>
                        E-Mail
                        <input name="email" type="email" required placeholder="name@example.de">
                    </label>
                    <label>
                        Gruppe
                        <input name="group" type="text" maxlength="80" list="recipient-groups" placeholder="z. B. Jugendwarte">
                    </label>
                    <label class="form-span-2">
                        Notiz
                        <textarea name="notes" rows="2" maxlength="500" placeholder="Interne Notiz, z. B. Funktion oder Zuständigkeit"></textarea>
                    </label>
                </div>
                <button type="submit" class="btn-primary btn-inline">Hinzufügen</button>
            </form>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2>Gespeicherte Empfänger</h2>
                <p class="count"><?= count($recipients) ?> Einträge · <?= count($groups) ?> Gruppen</p>
            </div>

            <?php if ($recipients === []): ?>
                <p class="hint">Noch keine Empfänger hinterlegt.</p>
            <?php else: ?>
                <?php foreach ($groupedRecipients as $groupLabel => $people): ?>
                    <section class="recipient-group-block">
                        <h3 class="recipient-group-title">
                            <span class="group-badge"><?= e($groupLabel) ?></span>
                            <span class="hint"><?= count($people) ?> Empfänger</span>
                        </h3>
                        <ul class="recipient-admin-list">
                            <?php foreach ($people as $person): ?>
                                <li class="recipient-admin-item" data-id="<?= e($person['id']) ?>">
                                    <div class="recipient-admin-view">
                                        <div>
                                            <strong><?= e($person['name'] !== '' ? $person['name'] : $person['email']) ?></strong>
                                            <?php if ($person['name'] !== ''): ?>
                                                <small><?= e($person['email']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($person['notes'] !== ''): ?>
                                                <p class="recipient-note"><?= e($person['notes']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="recipient-admin-actions">
                                            <button type="button" class="btn-ghost btn-small" data-edit>Bearbeiten</button>
                                            <form method="post" action="recipients.php" class="inline-form" onsubmit="return confirm('Empfänger wirklich entfernen?');">
                                                <input type="hidden" name="csrf" value="<?= e(Csrf::token()) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= e($person['id']) ?>">
                                                <button type="submit" class="btn-ghost btn-small btn-danger">Entfernen</button>
                                            </form>
                                        </div>
                                    </div>
                                    <form class="recipient-form recipient-admin-edit" method="post" action="recipients.php" hidden>
                                        <input type="hidden" name="csrf" value="<?= e(Csrf::token()) ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= e($person['id']) ?>">
                                        <div class="form-grid">
                                            <label>
                                                Name
                                                <input name="name" type="text" maxlength="120" value="<?= e($person['name']) ?>">
                                            </label>
                                            <label>
                                                E-Mail
                                                <input name="email" type="email" required value="<?= e($person['email']) ?>">
                                            </label>
                                            <label>
                                                Gruppe
                                                <input name="group" type="text" maxlength="80" list="recipient-groups" value="<?= e($person['group']) ?>">
                                            </label>
                                            <label class="form-span-2">
                                                Notiz
                                                <textarea name="notes" rows="2" maxlength="500"><?= e($person['notes']) ?></textarea>
                                            </label>
                                        </div>
                                        <div class="recipient-admin-actions">
                                            <button type="submit" class="btn-primary btn-small">Speichern</button>
                                            <button type="button" class="btn-ghost btn-small" data-cancel>Abbrechen</button>
                                        </div>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <datalist id="recipient-groups">
        <?php foreach ($groups as $groupLabel): ?>
            <?php if ($groupLabel !== 'Ohne Gruppe'): ?>
                <option value="<?= e($groupLabel) ?>"></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </datalist>

    <script src="assets/js/recipients.js"></script>
    <script src="assets/js/smtp.js"></script>
</body>
</html>

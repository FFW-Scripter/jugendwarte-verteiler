<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$auth->requireLogin();

$id = trim((string) ($_GET['id'] ?? ''));
$entry = $id !== '' ? $historyStore->get($id) : null;
$entries = $id === '' ? $historyStore->all() : [];
$title = $config->appTitle();
$flash = flash_take();

if ($id !== '' && $entry === null) {
    flash_set('error', 'Dieser Historieneintrag wurde nicht gefunden.');
    header('Location: history.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $entry ? 'Nachricht vom ' . e(format_datetime($entry['sent_at'])) : 'Historie' ?> · <?= e($title) ?></title>
    <?php render_head_meta(); ?>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <?php render_topbar('history', $entry ? 'Gesendete Nachricht' : 'Historie', $title); ?>

    <main class="layout layout-single">
        <?php if ($flash): ?>
            <p class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>

        <?php if ($entry !== null): ?>
            <p><a class="btn-ghost" href="history.php">← Zur Übersicht</a></p>

            <section class="panel">
                <p class="eyebrow"><?= e(format_datetime($entry['sent_at'])) ?></p>
                <h2 class="history-subject"><?= e($entry['subject'] !== '' ? $entry['subject'] : '(ohne Betreff)') ?></h2>
            </section>

            <section class="panel">
                <h2>Nachricht</h2>
                <div class="history-body editor"><?= $entry['body'] ?></div>
            </section>

            <section class="panel">
                <h2>Anhänge</h2>
                <?php if ($entry['attachments'] === []): ?>
                    <p class="hint">Keine Dateianhänge.</p>
                <?php else: ?>
                    <ul class="file-list">
                        <?php foreach ($entry['attachments'] as $file): ?>
                            <li>
                                <a href="history-file.php?id=<?= e($entry['id']) ?>&amp;file=<?= e(rawurlencode($file['stored'])) ?>">
                                    <?= e($file['name']) ?>
                                </a>
                                <span class="hint"><?= e(format_bytes($file['size'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h2>Empfänger (BCC)</h2>
                <p class="count"><?= count($entry['recipients']) ?> Adressen</p>
                <ul class="history-recipients">
                    <?php foreach ($entry['recipients'] as $person): ?>
                        <li>
                            <strong><?= e($person['name'] !== '' ? $person['name'] : $person['email']) ?></strong>
                            <?php if ($person['name'] !== ''): ?>
                                <small><?= e($person['email']) ?></small>
                            <?php endif; ?>
                            <?php if ($person['group'] !== ''): ?>
                                <span class="group-badge"><?= e($person['group']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php else: ?>
            <section class="panel">
                <div class="panel-head">
                    <h2>Gesendete Nachrichten</h2>
                    <p class="count"><?= count($entries) ?> Einträge</p>
                </div>
                <p class="hint">Gespeichert werden Betreff, Nachricht, Anhänge und die Empfängerliste nach dem erfolgreichen Versand.</p>

                <?php if ($entries === []): ?>
                    <p class="hint">Noch keine Nachrichten in der Historie.</p>
                <?php else: ?>
                    <ul class="history-list">
                        <?php foreach ($entries as $row): ?>
                            <li>
                                <a class="history-item" href="history.php?id=<?= e($row['id']) ?>">
                                    <span class="history-item-meta"><?= e(format_datetime($row['sent_at'])) ?></span>
                                    <strong><?= e($row['subject'] !== '' ? $row['subject'] : '(ohne Betreff)') ?></strong>
                                    <span class="hint">
                                        <?= (int) $row['recipient_count'] ?> Empfänger
                                        · <?= (int) $row['attachment_count'] ?> <?= (int) $row['attachment_count'] === 1 ? 'Anhang' : 'Anhänge' ?>
                                    </span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/smtp.js"></script>
</body>
</html>

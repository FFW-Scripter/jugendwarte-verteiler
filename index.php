<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$auth->requireLogin();

$recipients = $recipientStore->all();
$flash = flash_take();
$signatureHtml = $config->signatureEnabled() ? $config->signatureHtml() : '';
$title = $config->appTitle();
$oldSubject = (string) ($_SESSION['draft_subject'] ?? '');
$oldBody = (string) ($_SESSION['draft_body'] ?? '');
unset($_SESSION['draft_subject'], $_SESSION['draft_body']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true"></span>
            <div>
                <p class="eyebrow">Interner Verteiler</p>
                <h1><?= e($title) ?></h1>
                <?php if ($config->appSubtitle() !== ''): ?>
                    <p class="subtitle"><?= e($config->appSubtitle()) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="topbar-actions">
            <a class="btn-ghost" href="recipients.php">Empfänger</a>
            <button type="button" class="btn-ghost" id="smtp-test" data-csrf="<?= e(Csrf::token()) ?>">SMTP prüfen</button>
            <a class="btn-ghost" href="logout.php">Abmelden</a>
        </div>
    </header>

    <main class="layout">
        <?php if ($flash): ?>
            <p class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>
        <p id="smtp-test-result" class="notice" hidden role="status"></p>

        <?php if ($config->isPlaceholderSmtp()): ?>
            <p class="notice notice-warn">SMTP und Absender sind noch Platzhalter. Bitte <code>config.php</code> ausfüllen, bevor Nachrichten rausgehen.</p>
        <?php endif; ?>

        <?php if ($recipients === []): ?>
            <p class="notice notice-error">Es sind noch keine Empfänger hinterlegt. Bitte zuerst unter <a href="recipients.php">Empfänger</a> welche anlegen.</p>
        <?php endif; ?>

        <form id="compose-form" class="compose" method="post" action="send.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(Csrf::token()) ?>">
            <textarea id="body" name="body" hidden><?= e($oldBody) ?></textarea>

            <section class="panel">
                <label for="subject">Betreff</label>
                <input id="subject" name="subject" type="text" maxlength="180" required value="<?= e($oldSubject) ?>" placeholder="z. B. Übung am Samstag">

                <div class="editor-label">
                    <span>Nachricht</span>
                    <span class="hint">Formatierung über die Leiste. Die Signatur wird automatisch angehängt.</span>
                </div>
                <div class="editor-shell">
                    <div id="toolbar" class="toolbar" role="toolbar" aria-label="Textformatierung">
                        <button type="button" data-cmd="bold" title="Fett"><strong>B</strong></button>
                        <button type="button" data-cmd="italic" title="Kursiv"><em>I</em></button>
                        <button type="button" data-cmd="underline" title="Unterstrichen"><span class="u">U</span></button>
                        <button type="button" data-cmd="insertUnorderedList" title="Aufzählung">• Liste</button>
                        <button type="button" data-cmd="insertOrderedList" title="Nummerierung">1. Liste</button>
                        <button type="button" data-cmd="formatBlock" data-value="h2" title="Überschrift">H2</button>
                        <button type="button" data-cmd="createLink" title="Link">Link</button>
                        <button type="button" data-cmd="removeFormat" title="Formatierung entfernen">↺</button>
                    </div>
                    <div id="editor" class="editor" contenteditable="true" role="textbox" aria-label="Nachricht" data-placeholder="Nachricht an die Jugendwarte schreiben …"></div>
                </div>

                <?php if ($signatureHtml !== ''): ?>
                    <details class="signature" open>
                        <summary>Signatur (aus der Konfiguration)</summary>
                        <div class="signature-body"><?= $signatureHtml ?></div>
                    </details>
                <?php endif; ?>
            </section>

            <aside class="side">
                <section class="panel">
                    <h2>Anhänge</h2>
                    <p class="hint">Maximal <?= (int) $config->maxAttachments() ?> Dateien, insgesamt <?= e(format_bytes($config->maxAttachmentBytes())) ?>.</p>
                    <label class="file-btn">
                        Dateien hinzufügen
                        <input id="attachments" name="attachments[]" type="file" multiple data-max-files="<?= (int) $config->maxAttachments() ?>" data-max-bytes="<?= (int) $config->maxAttachmentBytes() ?>">
                    </label>
                    <ul id="file-list" class="file-list"></ul>
                </section>

                <section class="panel">
                    <h2>Empfänger (BCC)</h2>
                    <p class="hint">Jede Adresse geht nur als Blindkopie raus. Die Jugendwarte sehen sich gegenseitig nicht.</p>
                    <div class="recipient-toolbar">
                        <label class="toggle-all">
                            <input type="checkbox" id="toggle-recipients" checked>
                            <span>Alle auswählen</span>
                        </label>
                        <p class="count"><span id="selected-count"><?= count($recipients) ?></span> von <?= count($recipients) ?> ausgewählt</p>
                    </div>
                    <ul class="recipients">
                        <?php foreach ($recipients as $person): ?>
                            <li>
                                <label>
                                    <input type="checkbox" name="recipients[]" value="<?= e($person['email']) ?>" checked>
                                    <span>
                                        <strong><?= e($person['name'] !== '' ? $person['name'] : $person['email']) ?></strong>
                                        <?php if ($person['name'] !== ''): ?>
                                            <small><?= e($person['email']) ?></small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <button type="submit" class="btn-primary" <?= $recipients === [] ? 'disabled' : '' ?>>
                    Nachricht senden
                </button>
            </aside>
        </form>
    </main>

    <script src="assets/js/editor.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>

<?php

declare(strict_types=1);

/**
 * @param 'compose'|'recipients'|'history' $active
 */
function render_topbar(string $active, string $heading, string $subtitle = ''): void
{
    $csrf = Csrf::token();
    ?>
    <header class="topbar">
        <div class="topbar-left">
            <div class="brand">
                <span class="brand-mark" aria-hidden="true"></span>
                <div>
                    <p class="eyebrow">Interner Verteiler</p>
                    <h1><?= e($heading) ?></h1>
                    <?php if ($subtitle !== ''): ?>
                        <p class="subtitle"><?= e($subtitle) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="topbar-tools">
            <div class="site-nav-tool">
                <p class="site-nav-label" aria-hidden="true">&nbsp;</p>
                <div class="site-nav-links">
                    <button type="button" class="site-nav-link site-nav-link-button" id="smtp-test" data-csrf="<?= e($csrf) ?>">SMTP prüfen</button>
                </div>
            </div>
            <nav class="site-nav" aria-label="Hauptmenü">
                <p class="site-nav-label">Menü</p>
                <div class="site-nav-links">
                    <a class="site-nav-link<?= $active === 'compose' ? ' is-active' : '' ?>" href="index.php"<?= $active === 'compose' ? ' aria-current="page"' : '' ?>>Verteiler</a>
                    <a class="site-nav-link<?= $active === 'recipients' ? ' is-active' : '' ?>" href="recipients.php"<?= $active === 'recipients' ? ' aria-current="page"' : '' ?>>Empfänger</a>
                    <a class="site-nav-link<?= $active === 'history' ? ' is-active' : '' ?>" href="history.php"<?= $active === 'history' ? ' aria-current="page"' : '' ?>>Historie</a>
                    <a class="site-nav-link site-nav-link-logout" href="logout.php" aria-label="Abmelden">
                        <svg class="site-nav-link-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path fill="currentColor" d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.59L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                        </svg>
                        <span>Abmelden</span>
                    </a>
                </div>
            </nav>
        </div>
    </header>
    <p id="smtp-test-result" class="notice layout-notice" hidden role="status"></p>
    <?php
}

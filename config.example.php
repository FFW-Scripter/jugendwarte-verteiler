<?php

declare(strict_types=1);

/**
 * Vorlage: nach config.php kopieren und Werte anpassen.
 * config.php ist per .htaccess vom direkten Webzugriff ausgeschlossen.
 */
return [
    'app' => [
        'title' => 'Jugendwarte-Verteiler',
        'subtitle' => 'Nachrichten an die Jugendwarte',
        // Passwort für die Weboberfläche (Klartext oder password_hash)
        'password' => 'bitte-aendern',
        'max_attachment_bytes' => 12 * 1024 * 1024,
        'max_attachments' => 8,
        'session_lifetime' => 8 * 3600,
    ],

    'smtp' => [
        'host' => 'smtp.example.de',
        'port' => 587,
        // tls = STARTTLS (meist Port 587), ssl = SMTPS (meist Port 465), none = unverschlüsselt
        'encryption' => 'tls',
        'username' => 'absender@example.de',
        'password' => 'smtp-passwort',
        'timeout' => 30,
        'verify_peer' => true,
        'helo' => '',
        'debug' => false,
    ],

    'mail' => [
        'from_email' => 'absender@example.de',
        'from_name' => 'Jugendfeuerwehr',
        'reply_to' => '',
        // Sichtbares An-Feld; die Jugendwarte stehen nur im BCC
        'to_name' => 'Jugendwarte',
        'to_email' => 'absender@example.de',
    ],

    'recipients' => [
        ['name' => 'Max Mustermann', 'email' => 'max@example.de'],
        ['name' => 'Erika Muster', 'email' => 'erika@example.de'],
    ],

    'signature' => [
        'enabled' => true,
        'html' => <<<'HTML'
<p>Mit freundlichen Grüßen</p>
<p><strong>Jugendfeuerwehr</strong><br>
Jugendwart</p>
HTML,
    ],
];

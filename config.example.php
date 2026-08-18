<?php

declare(strict_types=1);

/**
 * Beispiel-Konfiguration für den Jugendwarte-Verteiler.
 *
 * Einrichtung:
 *   cp config.example.php config.php
 *
 * Anschließend config.php anpassen. Die Datei ist per .htaccess vor direktem
 * Webzugriff geschützt und sollte nicht ins Git-Repository.
 *
 * Empfänger werden separat in data/recipients.json gepflegt (Menü „Empfänger“).
 * Optional können beim allerersten Start noch Einträge aus dem Block
 * `recipients` unten automatisch übernommen werden, falls die JSON-Datei fehlt.
 */
return [
    'app' => [
        // Titel in der Browser-Leiste und im Kopfbereich
        'title' => 'Jugendwarte-Verteiler',

        // Optionaler Untertitel unter dem Titel
        'subtitle' => 'Nachrichten an die Jugendwarte',

        // Login-Passwort für die Weboberfläche
        // Möglich als Klartext oder als password_hash()-Ausgabe
        'password' => 'bitte-aendern',

        // Maximale Gesamtgröße aller Anhänge in Bytes (12 MB)
        'max_attachment_bytes' => 12 * 1024 * 1024,

        // Maximale Anzahl Anhänge pro Nachricht
        'max_attachments' => 8,

        // Sitzungsdauer nach Login in Sekunden (8 Stunden)
        'session_lifetime' => 8 * 3600,

        // Zeitzone für Historie und Zeitstempel (PHP-Zeitzonen-ID)
        'timezone' => 'Europe/Berlin',
    ],

    'smtp' => [
        // SMTP-Server des Mail-Anbieters
        'host' => 'smtp.example.de',

        // Übliche Ports: 587 (STARTTLS), 465 (SSL)
        'port' => 587,

        // Verschlüsselung: tls | ssl | none
        'encryption' => 'tls',

        // SMTP-Benutzername (meist die Absenderadresse)
        'username' => 'absender@example.de',

        // SMTP-Passwort oder App-Passwort
        'password' => 'smtp-passwort',

        // Timeout für die SMTP-Verbindung in Sekunden
        'timeout' => 30,

        // TLS-Zertifikat prüfen (bei Problemen testweise false)
        'verify_peer' => true,

        // Optionaler HELO/EHLO-Name; leer = automatisch
        'helo' => '',

        // true schreibt SMTP-Fehler ins PHP-Error-Log
        'debug' => false,
    ],

    'mail' => [
        // Sichtbare Absenderadresse
        'from_email' => 'absender@example.de',

        // Anzeigename des Absenders
        'from_name' => 'Jugendfeuerwehr',

        // Optional: Reply-To-Adresse (leer = kein Reply-To-Header)
        'reply_to' => '',

        // Sichtbares An-Feld in der Mail (Empfänger stehen nur im BCC)
        'to_name' => 'Jugendwarte',
        'to_email' => 'absender@example.de',
    ],

    // Optional: nur für die einmalige Migration beim ersten Start.
    // Danach Empfänger über die Oberfläche unter „Empfänger“ pflegen.
    /*
    'recipients' => [
        ['name' => 'Max Mustermann', 'email' => 'max@example.de'],
        ['name' => 'Erika Muster', 'email' => 'erika@example.de'],
    ],
    */

    'signature' => [
        // Signatur automatisch an jede Nachricht anhängen
        'enabled' => true,

        // HTML-Signatur (erlaubte Tags wie im Editor)
        'html' => <<<'HTML'
<p>Mit freundlichen Grüßen</p>
<p><strong>Jugendfeuerwehr</strong><br>
Jugendwart</p>
HTML,
    ],
];

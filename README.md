# Jugendwarte-Verteiler

Interner Mail-Verteiler ohne Composer und ohne JavaScript-Bibliotheken. PHP 8.3 spricht SMTP direkt an, die Oberfläche bringt einen kleinen HTML-Editor mit, Anhänge gehen mit, und die Jugendwarte stehen nur im BCC.

## Einrichtung

1. `config.example.php` nach `config.php` kopieren, falls die Datei fehlt.
2. In `config.php` ausfüllen:
   - `app.password` — Zugang zur Oberfläche (nicht den Platzhalter `bitte-aendern` lassen)
   - `smtp.*` — Host, Port, Verschlüsselung, Benutzername, Passwort
   - `mail.from_email` / `mail.from_name` — sichtbare Absenderadresse
   - `recipients` — Namen und Adressen der Jugendwarte
   - `signature.html` — Standardsignatur unter jeder Nachricht
3. Aufruf im Browser: `http://localhost/jugendwarte-verteiler/`

Apache braucht `mod_rewrite` nicht. Für verschlüsseltes SMTP muss PHP `openssl` laden; für die Typprüfung der Anhänge ist `fileinfo` sinnvoll.

## SMTP-Hinweise

| Anbieter | Host | Port | encryption |
| --- | --- | --- | --- |
| Viele Hoster | `smtp.…` | 587 | `tls` (STARTTLS) |
| SMTPS | `smtp.…` | 465 | `ssl` |
| Gmail | `smtp.gmail.com` | 587 | `tls` plus App-Passwort |

Bei selbstsignierten Zertifikaten kann `verify_peer` vorübergehend auf `false` stehen. Für Fehlersuche `smtp.debug` auf `true` setzen und das PHP-Error-Log lesen.

## Versand

- Das sichtbare An-Feld kommt aus `mail.to_name` / `mail.to_email`.
- Die eigentlichen Empfänger stehen nur im Umschlag als BCC. Sie sehen sich gegenseitig nicht.
- Einzelne Adressen lassen sich vor dem Senden abwählen; es gehen nur Adressen aus der Config raus.

## Anhänge

Erlaubt: PDF, ZIP, Bilder, Text/CSV sowie Office-Dateien (doc, docx, xls, xlsx, odt, ods). Größe und Anzahl stehen in `app.max_attachment_bytes` und `app.max_attachments`. Zusätzlich gelten `upload_max_filesize` und `post_max_size` in der `php.ini`.

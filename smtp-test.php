<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Bitte neu anmelden.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Nur POST ist erlaubt.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if ($csrf === '') {
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json) && isset($json['csrf'])) {
        $csrf = (string) $json['csrf'];
    }
}

if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Sitzung ungültig. Bitte die Seite neu laden.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$smtp = new SmtpClient($config);

try {
    $smtp->probe();
    $user = $config->smtpUsername();
    $target = $config->smtpHost() . ':' . $config->smtpPort();
    $message = $user === ''
        ? 'SMTP-Verbindung zu ' . $target . ' erfolgreich. Es ist kein Benutzername hinterlegt.'
        : 'SMTP-Anmeldung erfolgreich: ' . $target . ' als ' . $user . '.';
    echo json_encode(['ok' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    $message = $e->getMessage();
    if ($config->smtpDebug()) {
        error_log('Jugendwarte-Verteiler SMTP-Test: ' . $e->getMessage() . "\n" . implode("\n", $smtp->transcript()));
        $message .= ' SMTP-Protokoll steht im PHP-Error-Log.';
    }
    echo json_encode(['ok' => false, 'message' => 'SMTP-Test fehlgeschlagen: ' . $message], JSON_UNESCAPED_UNICODE);
}

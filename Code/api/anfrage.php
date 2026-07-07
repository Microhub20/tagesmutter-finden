<?php
/**
 * Kontaktanfrage von Eltern an eine Tagesmutter.
 * Speichert die Anfrage (Historie fürs Konto) und schickt zusätzlich eine E-Mail.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tmf_json(['error' => 'method_not_allowed'], 405);
if (!empty($_POST['firma_website'])) tmf_json(['ok' => true]); // Honeypot

$clean = static fn($s, $m) => mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)$s)), 0, $m);
$tmId      = $clean($_POST['id'] ?? '', 64);
$name      = $clean($_POST['name'] ?? '', 80);
$email     = mb_strtolower($clean($_POST['email'] ?? '', 120));
$tel       = $clean($_POST['tel'] ?? '', 40);
$nachricht = mb_substr(trim((string)($_POST['nachricht'] ?? '')), 0, 1500);

$fehler = [];
if ($name === '')                                $fehler[] = 'Name fehlt';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $fehler[] = 'E-Mail ungültig';
if (mb_strlen($nachricht) < 10)                  $fehler[] = 'Bitte etwas mehr schreiben (min. 10 Zeichen)';
if ($fehler) tmf_json(['error' => implode(', ', $fehler)], 422);

try {
    $pdo = tmf_db();
    $stmt = $pdo->prepare("SELECT name, email FROM tagesmuetter WHERE id = ? AND status = 'approved'");
    $stmt->execute([$tmId]);
    $tm = $stmt->fetch();
    if (!$tm) tmf_json(['error' => 'not_found'], 404);

    // Anfrage speichern (kommt so nie verloren, auch wenn die Mail scheitert)
    $aid = bin2hex(random_bytes(12));
    $pdo->prepare("INSERT INTO anfragen (id, tm_id, name, email, tel, nachricht) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$aid, $tmId, $name, $email, $tel, $nachricht]);

    // E-Mail an die Tagesmutter (best effort – Zustellung hängt vom Hoster ab)
    $betreff = 'Neue Betreuungsanfrage über mein Tageskind';
    $body = "Hallo {$tm['name']},\n\n"
          . "über dein Profil auf \"mein Tageskind\" hat dich jemand kontaktiert:\n\n"
          . "Name:    {$name}\n"
          . "E-Mail:  {$email}\n"
          . "Telefon: " . ($tel !== '' ? $tel : '—') . "\n\n"
          . "Nachricht:\n{$nachricht}\n\n"
          . "Du kannst direkt auf diese E-Mail antworten. Die Anfrage findest du auch jederzeit in deinem Konto.";
    $headers = "From: mein Tageskind <noreply@mein-tageskind.de>\r\n"
             . "Reply-To: " . $name . " <" . $email . ">\r\n"
             . "Content-Type: text/plain; charset=utf-8\r\n";
    @mail($tm['email'], '=?UTF-8?B?' . base64_encode($betreff) . '?=', $body, $headers);

    tmf_json(['ok' => true]);
} catch (Throwable $e) {
    tmf_json(['error' => 'server_error'], 500);
}

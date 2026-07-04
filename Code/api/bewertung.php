<?php
/**
 * Eltern hinterlassen eine Bewertung/Empfehlung (Status "pending" → Admin gibt frei).
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tmf_json(['error' => 'method_not_allowed'], 405);
if (!empty($_POST['firma_website'])) tmf_json(['ok' => true]); // Honeypot

$clean  = fn($s, $m) => mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)$s)), 0, $m);
$tmId   = $clean($_POST['id'] ?? '', 64);
$name   = $clean($_POST['name'] ?? '', 80);
$sterne = max(0, min(5, (int)($_POST['sterne'] ?? 0)));
$text   = mb_substr(trim((string)($_POST['text'] ?? '')), 0, 800);

$fehler = [];
if ($name === '')          $fehler[] = 'Name fehlt';
if ($sterne < 1)           $fehler[] = 'Bitte Sterne wählen';
if (mb_strlen($text) < 10) $fehler[] = 'Bitte etwas mehr schreiben (min. 10 Zeichen)';
if ($fehler) tmf_json(['error' => implode(', ', $fehler)], 422);

try {
    $pdo = tmf_db();
    $chk = $pdo->prepare("SELECT 1 FROM tagesmuetter WHERE id = ? AND status = 'approved'");
    $chk->execute([$tmId]);
    if (!$chk->fetchColumn()) tmf_json(['error' => 'not_found'], 404);
    $pdo->prepare("INSERT INTO bewertungen (id, tm_id, name, sterne, text, status) VALUES (?, ?, ?, ?, ?, 'pending')")
        ->execute([bin2hex(random_bytes(12)), $tmId, $name, $sterne, $text]);
    tmf_json(['ok' => true]);
} catch (Throwable $e) {
    tmf_json(['error' => 'server_error'], 500);
}

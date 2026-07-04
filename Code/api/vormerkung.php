<?php
/**
 * Eltern merken sich bei einer (vollen) Tagesmutter vor, um bei freiem Platz
 * benachrichtigt zu werden. Landet in Tabelle `vormerkungen`.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') tmf_json(['error' => 'method_not_allowed'], 405);
if (!empty($_POST['firma_website'])) tmf_json(['ok' => true]); // Honeypot

$clean = fn($s, $m) => mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)$s)), 0, $m);
$tmId  = $clean($_POST['id'] ?? '', 64);
$name  = $clean($_POST['name'] ?? '', 80);
$email = mb_strtolower($clean($_POST['email'] ?? '', 120));

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    tmf_json(['error' => 'Bitte Name und gültige E-Mail angeben'], 422);
}

try {
    $pdo = tmf_db();
    $chk = $pdo->prepare("SELECT 1 FROM tagesmuetter WHERE id = ? AND status = 'approved'");
    $chk->execute([$tmId]);
    if (!$chk->fetchColumn()) tmf_json(['error' => 'not_found'], 404);
    $pdo->prepare("INSERT INTO vormerkungen (id, tm_id, name, email) VALUES (?, ?, ?, ?)")
        ->execute([bin2hex(random_bytes(12)), $tmId, $name, $email]);
    tmf_json(['ok' => true]);
} catch (Throwable $e) {
    tmf_json(['error' => 'server_error'], 500);
}

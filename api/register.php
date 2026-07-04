<?php
/**
 * Registrierung: legt eine Tagesmutter MIT Account an (E-Mail + Passwort).
 * Profil-Status "pending" (Freigabe durch Super-Admin). Loggt danach direkt ein.
 */
declare(strict_types=1);
require __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    tmf_json(['error' => 'method_not_allowed'], 405);
}
if (!empty($_POST['firma_website'])) tmf_json(['ok' => true]); // Honeypot

$clean = static fn(string $s, int $max): string =>
    mb_substr(trim(str_replace(["\r", "\n"], ' ', $s)), 0, $max);

$name        = $clean($_POST['name'] ?? '', 80);
$ort         = $clean($_POST['ort'] ?? '', 80);
$plaetze     = max(0, min(9, (int)($_POST['plaetze'] ?? 0)));
$zeiten      = $clean($_POST['zeiten'] ?? '', 120);
$email       = mb_strtolower($clean($_POST['email'] ?? '', 120));
$tel         = $clean($_POST['tel'] ?? '', 40);
$erlaubnis   = !empty($_POST['erlaubnis']) ? 1 : 0;
$consent     = !empty($_POST['consent']);
$persoenlich = mb_substr(trim((string)($_POST['persoenlich'] ?? '')), 0, 1500);
$pass        = (string)($_POST['passwort'] ?? '');

$alter = $_POST['alter'] ?? [];
if (is_string($alter)) $alter = array_filter(array_map('trim', explode(',', $alter)));
$erlaubteAlter = ['0–1 Jahr', '1–3 Jahre', '3+ Jahre'];
$alter = array_values(array_intersect($erlaubteAlter, (array)$alter));

$fehler = [];
if ($name === '')            $fehler[] = 'Name fehlt';
if ($ort === '')             $fehler[] = 'Stadtteil fehlt';
if ($zeiten === '')          $fehler[] = 'Betreuungszeiten fehlen';
if (!$alter)                 $fehler[] = 'mindestens eine Altersgruppe';
if ($persoenlich === '')     $fehler[] = 'persönliche Vorstellung fehlt';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fehler[] = 'E-Mail ungültig';
if (mb_strlen($pass) < 8)    $fehler[] = 'Passwort mind. 8 Zeichen';
if (!$consent)               $fehler[] = 'Einwilligung erforderlich';
if ($fehler) tmf_json(['error' => implode(', ', $fehler)], 422);

if (tmf_find_by_email($email)) {
    tmf_json(['error' => 'Diese E-Mail ist bereits registriert – bitte einloggen.'], 409);
}

// Foto (optional)
$fotoName = null;
if (!empty($_FILES['foto']['tmp_name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
    $f = $_FILES['foto'];
    if ($f['size'] > 4 * 1024 * 1024) tmf_json(['error' => 'Foto zu groß (max. 4 MB)'], 422);
    $info = @getimagesize($f['tmp_name']);
    $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($extByMime[$info['mime']])) tmf_json(['error' => 'Nur JPG, PNG oder WebP'], 422);
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $fotoName = bin2hex(random_bytes(8)) . '.' . $extByMime[$info['mime']];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $fotoName)) $fotoName = null;
}

// ID aus Name ableiten
$slug = strtolower($name);
$slug = strtr($slug, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);
$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
$slug = trim($slug, '-') ?: 'eintrag';
$id   = $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 6);

try {
    $pdo = tmf_db();
    $nummer = tmf_next_nummer($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO tagesmuetter
         (id, name, ort, plaetze, zeiten, altersgruppen, persoenlich, email, tel, erlaubnis, foto, passwort_hash, nummer, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([
        $id, $name, $ort, $plaetze, $zeiten,
        json_encode($alter, JSON_UNESCAPED_UNICODE),
        $persoenlich, $email, $tel, $erlaubnis, $fotoName, tmf_hash_pw($pass), $nummer,
    ]);
    // direkt einloggen
    tmf_session();
    session_regenerate_id(true);
    $_SESSION['tmf_uid'] = $id;
    tmf_json(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    tmf_json(['error' => 'server_error'], 500);
}

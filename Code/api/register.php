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
$bundesland  = $clean($_POST['bundesland'] ?? '', 60);
$plaetze     = max(0, min(9, (int)($_POST['plaetze'] ?? 0)));
$zeiten      = $clean($_POST['zeiten'] ?? '', 120);
$email       = mb_strtolower($clean($_POST['email'] ?? '', 120));
$tel         = $clean($_POST['tel'] ?? '', 40);
$erlaubnis   = !empty($_POST['erlaubnis']) ? 1 : 0;
$consent     = !empty($_POST['consent']);
$persoenlich = mb_substr(trim((string)($_POST['persoenlich'] ?? '')), 0, 1500);
$pass        = (string)($_POST['passwort'] ?? '');
$qualifikation = $clean($_POST['qualifikation'] ?? '', 120);
$sprachen      = $clean($_POST['sprachen'] ?? '', 120);
$frei_ab       = $clean($_POST['frei_ab'] ?? '', 40);
$ernaehrung    = $clean($_POST['ernaehrung'] ?? '', 120);
$haustiere     = $clean($_POST['haustiere'] ?? '', 80);
$nichtraucher  = !empty($_POST['nichtraucher']) ? 1 : 0;
$konzept       = mb_substr(trim((string)($_POST['konzept'] ?? '')), 0, 400);
$extras        = array_values(array_intersect(['Frühbetreuung','Randzeiten','Wochenende','Ferienbetreuung','Notfallbetreuung'], (array)($_POST['extras'] ?? [])));

$alter = $_POST['alter'] ?? [];
if (is_string($alter)) $alter = array_filter(array_map('trim', explode(',', $alter)));
$erlaubteAlter = ['0–1 Jahr', '1–3 Jahre', '3+ Jahre'];
$alter = array_values(array_intersect($erlaubteAlter, (array)$alter));

$fehler = [];
if ($name === '')            $fehler[] = 'Name fehlt';
if ($ort === '')             $fehler[] = 'Ort fehlt';
if ($bundesland === '')      $fehler[] = 'Bundesland fehlt';
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

// Bilder (optional): 1 Profilbild + bis zu 5 Galerie-Bilder (clientseitig verkleinert)
$dir = __DIR__ . '/../uploads';
$fotoName = !empty($_FILES['foto']['tmp_name'])
    ? tmf_save_image($_FILES['foto']['tmp_name'], (int)($_FILES['foto']['size'] ?? 0), $dir)
    : null;
$galerie = [];
if (!empty($_FILES['galerie']['tmp_name']) && is_array($_FILES['galerie']['tmp_name'])) {
    foreach ($_FILES['galerie']['tmp_name'] as $i => $tmp) {
        if (count($galerie) >= 5) break;
        $nm = tmf_save_image((string)$tmp, (int)($_FILES['galerie']['size'][$i] ?? 0), $dir);
        if ($nm) $galerie[] = $nm;
    }
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
         (id, name, ort, bundesland, plaetze, zeiten, altersgruppen, persoenlich, email, tel, erlaubnis, foto, fotos, qualifikation, sprachen, frei_ab, ernaehrung, nichtraucher, haustiere, konzept, extras, passwort_hash, nummer, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->execute([
        $id, $name, $ort, $bundesland, $plaetze, $zeiten,
        json_encode($alter, JSON_UNESCAPED_UNICODE),
        $persoenlich, $email, $tel, $erlaubnis, $fotoName,
        json_encode($galerie, JSON_UNESCAPED_UNICODE),
        $qualifikation, $sprachen, $frei_ab, $ernaehrung, $nichtraucher, $haustiere, $konzept,
        json_encode($extras, JSON_UNESCAPED_UNICODE),
        tmf_hash_pw($pass), $nummer,
    ]);
    // direkt einloggen
    tmf_session();
    session_regenerate_id(true);
    $_SESSION['tmf_uid'] = $id;
    tmf_json(['ok' => true, 'id' => $id]);
} catch (Throwable $e) {
    tmf_json(['error' => 'server_error'], 500);
}

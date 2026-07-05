<?php
/**
 * TEMPORÄRES Selbsttest-Skript (wird nach dem SSR-Test wieder entfernt).
 * Legt EINEN klar markierten, approved Test-Eintrag an bzw. löscht ihn wieder,
 * damit sich der server-seitige Profil-/Listen-Render einmal live verifizieren lässt.
 * Nur mit Token ausführbar (kein öffentlicher Missbrauch).
 */
declare(strict_types=1);
require __DIR__ . '/api/db.php';

$TOKEN  = 'ssr-check-7Qx9';
$TESTID = 'ssrtest-demo-000000';
if (($_GET['t'] ?? '') !== $TOKEN) { http_response_code(403); exit('forbidden'); }

$pdo = tmf_db();
// immer erst den evtl. vorhandenen Test-Eintrag entfernen (idempotent)
$pdo->prepare("DELETE FROM tagesmuetter WHERE id = ?")->execute([$TESTID]);
if (isset($_GET['clean'])) { header('Content-Type: text/plain'); exit('cleaned ' . $TESTID); }

$stmt = $pdo->prepare(
    "INSERT INTO tagesmuetter
     (id,name,ort,plaetze,zeiten,altersgruppen,persoenlich,email,tel,erlaubnis,bundesland,qualifikation,sprachen,frei_ab,status,agb_version,created_at)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'approved',?,?)"
);
$stmt->execute([
    $TESTID,
    'Testeintrag SSR (bitte ignorieren)',
    'Balingen',
    2,
    'Mo–Fr 7–17 Uhr',
    json_encode(['1–3 Jahre', '3+ Jahre'], JSON_UNESCAPED_UNICODE),
    'Temporärer Testeintrag zur Verifikation der serverseitigen Darstellung. Wird sofort wieder gelöscht.',
    'test@example.invalid',
    '',
    1,
    'Baden-Württemberg',
    'Qualifizierte Tagespflegeperson (§ 43 SGB VIII)',
    'Deutsch',
    'sofort',
    TMF_AGB_VERSION,
    date('Y-m-d H:i:s'),
]);
header('Content-Type: text/plain');
exit('seeded ' . $TESTID);

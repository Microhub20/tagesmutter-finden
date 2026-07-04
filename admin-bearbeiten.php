<?php
/**
 * Super-Admin: beliebiges Tagesmutter-Profil bearbeiten (inkl. Status).
 * Zugang nur mit aktiver Admin-Session (siehe admin.php).
 */
declare(strict_types=1);
session_start();
require __DIR__ . '/api/db.php';

if (empty($_SESSION['tmf_admin'])) { header('Location: admin.php'); exit; }

$pdo = tmf_db();
$id  = (string)($_GET['id'] ?? $_POST['id'] ?? '');

// --- Speichern ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $clean = fn($s, $m) => mb_substr(trim(str_replace(["\r", "\n"], ' ', (string)$s)), 0, $m);
    $alter = array_values(array_intersect(['0–1 Jahr', '1–3 Jahre', '3+ Jahre'], (array)($_POST['alter'] ?? [])));
    if (!$alter) $alter = ['1–3 Jahre'];

    if (!empty($_POST['foto_entfernen'])) {
        $rf = $pdo->prepare("SELECT foto FROM tagesmuetter WHERE id=?"); $rf->execute([$id]);
        if ($foto = $rf->fetchColumn()) @unlink(__DIR__ . '/uploads/' . $foto);
        $pdo->prepare("UPDATE tagesmuetter SET foto=NULL WHERE id=?")->execute([$id]);
    }
    // Galerie: angehakte Bilder entfernen
    $gf = $pdo->prepare("SELECT fotos FROM tagesmuetter WHERE id=?"); $gf->execute([$id]);
    $galerieAlt = tmf_fotos_list($gf->fetchColumn());
    $weg = (array)($_POST['galerie_weg'] ?? []);
    $galerieNeu = array_values(array_diff($galerieAlt, $weg));
    foreach (array_intersect($galerieAlt, $weg) as $f) @unlink(__DIR__ . '/uploads/' . $f);

    $status = in_array($_POST['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_POST['status'] : 'pending';
    $pdo->prepare(
        "UPDATE tagesmuetter SET name=?, ort=?, bundesland=?, plaetze=?, zeiten=?, altersgruppen=?, persoenlich=?, email=?, tel=?, erlaubnis=?, fotos=?, status=?, updated_at=CURRENT_TIMESTAMP WHERE id=?"
    )->execute([
        $clean($_POST['name'] ?? '', 80), $clean($_POST['ort'] ?? '', 80), $clean($_POST['bundesland'] ?? '', 60),
        max(0, min(9, (int)($_POST['plaetze'] ?? 0))),
        $clean($_POST['zeiten'] ?? '', 120), json_encode($alter, JSON_UNESCAPED_UNICODE),
        mb_substr(trim((string)($_POST['persoenlich'] ?? '')), 0, 1500),
        $clean($_POST['email'] ?? '', 120), $clean($_POST['tel'] ?? '', 40),
        !empty($_POST['erlaubnis']) ? 1 : 0, json_encode($galerieNeu, JSON_UNESCAPED_UNICODE), $status, $id,
    ]);
    header('Location: admin.php?msg=' . rawurlencode('Profil „' . $clean($_POST['name'] ?? '', 40) . '" gespeichert.'));
    exit;
}

$st = $pdo->prepare("SELECT * FROM tagesmuetter WHERE id=?");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { header('Location: admin.php'); exit; }

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$meinAlter = json_decode($r['altersgruppen'] ?: '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Profil bearbeiten – Admin</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="styles.css">
<style>
  .b-wrap{max-width:660px;margin:0 auto;padding:2rem 1.2rem 4rem}
  .b-card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:2.2rem;box-shadow:var(--shadow)}
  @media(max-width:600px){.b-card{padding:1.4rem}}
  .b-card h1{font-size:1.5rem;font-weight:800;margin-bottom:1.2rem}
  .b-back{display:inline-block;color:var(--muted);text-decoration:none;font-weight:800;font-size:.9rem;margin-bottom:1.2rem}
</style>
</head>
<body>
<div class="b-wrap">
  <a class="b-back" href="admin.php">← Zurück zur Übersicht</a>
  <div class="b-card">
    <h1>✏️ Profil bearbeiten</h1>
    <p style="color:var(--muted);margin-top:-.7rem;margin-bottom:1.3rem;font-weight:800">Mitgliedsnummer <?= tmf_usernr($r['nummer']) ?></p>
    <form method="post">
      <input type="hidden" name="id" value="<?= $e($r['id']) ?>">
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="pending"  <?= $r['status']==='pending'?'selected':'' ?>>🕓 Wartet auf Freigabe</option>
          <option value="approved" <?= $r['status']==='approved'?'selected':'' ?>>✓ Öffentlich sichtbar</option>
          <option value="rejected" <?= $r['status']==='rejected'?'selected':'' ?>>✕ Nicht sichtbar</option>
        </select>
      </div>
      <div class="field"><label for="in-name">Name</label><input type="text" id="in-name" name="name" maxlength="60" value="<?= $e($r['name']) ?>"></div>
      <div class="row">
        <div class="field"><label for="in-bundesland">Bundesland</label><select id="in-bundesland" name="bundesland"></select></div>
        <div class="field"><label for="in-ort">Stadt / Gemeinde</label><select id="in-ort" name="ort"></select></div>
      </div>
      <div class="row">
        <div class="field"><label for="in-plaetze">Freie Plätze</label>
          <select id="in-plaetze" name="plaetze">
            <?php for($i=0;$i<=4;$i++): ?><option value="<?= $i ?>" <?= (int)$r['plaetze']===$i?'selected':'' ?>><?= $i===0?'Warteliste':($i.' Plätze') ?></option><?php endfor; ?>
          </select>
        </div>
        <div class="field"><label for="in-zeiten">Betreuungszeiten</label><input type="text" id="in-zeiten" name="zeiten" maxlength="60" value="<?= $e($r['zeiten']) ?>"></div>
      </div>
      <div class="field">
        <label>Altersgruppen</label>
        <div class="age-boxes">
          <?php foreach(['0–1 Jahr','1–3 Jahre','3+ Jahre'] as $a): ?>
            <label><input type="checkbox" name="alter[]" value="<?= $e($a) ?>" <?= in_array($a,$meinAlter,true)?'checked':'' ?>> <?= $e($a) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if($r['foto']): ?>
      <div class="field">
        <label>Profilbild</label>
        <div style="display:flex;align-items:center;gap:1rem">
          <img src="uploads/<?= $e($r['foto']) ?>" alt="" style="width:64px;height:64px;border-radius:14px;object-fit:cover">
          <label class="toggle" style="display:inline-flex"><input type="checkbox" name="foto_entfernen" value="1"> Profilbild entfernen</label>
        </div>
      </div>
      <?php endif; ?>
      <?php $galerie = tmf_fotos_list($r['fotos'] ?? ''); if($galerie): ?>
      <div class="field">
        <label>Galerie-Bilder</label>
        <div class="galerie-preview">
          <?php foreach($galerie as $g): ?>
          <div style="text-align:center">
            <div class="g-thumb"><img src="uploads/<?= $e($g) ?>" alt=""></div>
            <label style="font-size:.7rem;display:block;margin-top:.25rem;color:var(--muted)"><input type="checkbox" name="galerie_weg[]" value="<?= $e($g) ?>"> entfernen</label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="field">
        <label for="in-text">Persönliche Vorstellung</label>
        <textarea id="in-text" name="persoenlich" rows="6" maxlength="1500"><?= $e($r['persoenlich']) ?></textarea>
      </div>
      <div class="row">
        <div class="field"><label for="in-email">E-Mail (Login)</label><input type="email" id="in-email" name="email" maxlength="100" value="<?= $e($r['email']) ?>"></div>
        <div class="field"><label for="in-tel">Telefon</label><input type="tel" id="in-tel" name="tel" maxlength="30" value="<?= $e($r['tel']) ?>"></div>
      </div>
      <div class="field">
        <label class="toggle" style="display:inline-flex"><input type="checkbox" name="erlaubnis" value="1" <?= $r['erlaubnis']?'checked':'' ?>> Pflegeerlaubnis nach §&nbsp;43 SGB&nbsp;VIII</label>
      </div>
      <div class="submit-row" style="text-align:left"><button type="submit" class="btn btn-coral">Speichern</button> &nbsp; <a href="admin.php" class="btn btn-ghost">Abbrechen</a></div>
    </form>
  </div>
</div>
<script src="data.js"></script>
<script>
  initOrtsauswahl(
    document.getElementById("in-bundesland"),
    document.getElementById("in-ort"),
    <?= json_encode($r['bundesland'] ?: 'Baden-Württemberg', JSON_UNESCAPED_UNICODE) ?>,
    <?= json_encode($r['ort'], JSON_UNESCAPED_UNICODE) ?>,
    false
  );
</script>
</body>
</html>

<?php
declare(strict_types=1);
require __DIR__ . '/api/auth.php';
$user = tmf_require_login();

// ---------- Speichern (POST via fetch, FormData → JSON) ----------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Konto komplett löschen (DSGVO – Recht auf Löschung)
    if (($_POST['aktion'] ?? '') === 'loeschen') {
        $db = tmf_db();
        if (!empty($user['foto'])) @unlink(__DIR__ . '/uploads/' . $user['foto']);
        foreach (tmf_fotos_list($user['fotos'] ?? '') as $g) @unlink(__DIR__ . '/uploads/' . $g);
        foreach (['anfragen', 'vormerkungen'] as $t) {
            $db->prepare("DELETE FROM {$t} WHERE tm_id = ?")->execute([$user['id']]);
        }
        $db->prepare("DELETE FROM tagesmuetter WHERE id = ?")->execute([$user['id']]);
        tmf_logout();
        tmf_json(['ok' => true, 'geloescht' => true]);
    }

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
    if ($name === '')        $fehler[] = 'Name fehlt';
    if ($ort === '')         $fehler[] = 'Ort fehlt';
    if ($bundesland === '')  $fehler[] = 'Bundesland fehlt';
    if ($zeiten === '')      $fehler[] = 'Betreuungszeiten fehlen';
    if (!$alter)             $fehler[] = 'mindestens eine Altersgruppe';
    if ($persoenlich === '') $fehler[] = 'persönliche Vorstellung fehlt';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fehler[] = 'E-Mail ungültig';
    $other = tmf_find_by_email($email);
    if ($other && $other['id'] !== $user['id']) $fehler[] = 'Diese E-Mail ist bereits vergeben';
    if ($pass !== '' && mb_strlen($pass) < 8) $fehler[] = 'neues Passwort mind. 8 Zeichen';
    if ($fehler) tmf_json(['error' => implode(', ', $fehler)], 422);

    // Foto (optional, ersetzt altes)
    $fotoName = null;
    if (!empty($_FILES['foto']['tmp_name']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
        $f = $_FILES['foto'];
        if ($f['size'] > 4 * 1024 * 1024) tmf_json(['error' => 'Foto zu groß (max. 4 MB)'], 422);
        $info = @getimagesize($f['tmp_name']);
        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!$info || !isset($extByMime[$info['mime']])) tmf_json(['error' => 'Nur JPG, PNG oder WebP'], 422);
        $dir = __DIR__ . '/uploads';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $fotoName = bin2hex(random_bytes(8)) . '.' . $extByMime[$info['mime']];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $fotoName)) $fotoName = null;
        elseif (!empty($user['foto'])) @unlink($dir . '/' . $user['foto']); // altes löschen
    }

    // Galerie: bestehende behalten (Checkbox), entfernte löschen, neue anhängen (max 5)
    $alteGalerie = tmf_fotos_list($user['fotos'] ?? '');
    $behalten    = array_values(array_intersect($alteGalerie, (array)($_POST['behalten'] ?? [])));
    foreach (array_diff($alteGalerie, $behalten) as $weg) @unlink(__DIR__ . '/uploads/' . $weg);
    $neueGalerie = $behalten;
    if (!empty($_FILES['galerie']['tmp_name']) && is_array($_FILES['galerie']['tmp_name'])) {
        foreach ($_FILES['galerie']['tmp_name'] as $i => $tmp) {
            if (count($neueGalerie) >= 5) break;
            $nm = tmf_save_image((string)$tmp, (int)($_FILES['galerie']['size'][$i] ?? 0), __DIR__ . '/uploads');
            if ($nm) $neueGalerie[] = $nm;
        }
    }

    $sql = "UPDATE tagesmuetter SET name=?, ort=?, bundesland=?, plaetze=?, zeiten=?, altersgruppen=?, persoenlich=?, email=?, tel=?, erlaubnis=?, fotos=?, qualifikation=?, sprachen=?, frei_ab=?, ernaehrung=?, nichtraucher=?, haustiere=?, konzept=?, extras=?, updated_at=CURRENT_TIMESTAMP";
    $params = [$name, $ort, $bundesland, $plaetze, $zeiten, json_encode($alter, JSON_UNESCAPED_UNICODE), $persoenlich, $email, $tel, $erlaubnis, json_encode($neueGalerie, JSON_UNESCAPED_UNICODE), $qualifikation, $sprachen, $frei_ab, $ernaehrung, $nichtraucher, $haustiere, $konzept, json_encode($extras, JSON_UNESCAPED_UNICODE)];
    if ($fotoName) {
        $sql .= ", foto=?"; $params[] = $fotoName;
    } elseif (!empty($_POST['foto_entfernen'])) {
        if (!empty($user['foto'])) @unlink(__DIR__ . '/uploads/' . $user['foto']);
        $sql .= ", foto=NULL";
    }
    if ($pass !== '') { $sql .= ", passwort_hash=?"; $params[] = tmf_hash_pw($pass); }
    $sql .= " WHERE id=?";
    $params[] = $user['id'];

    try {
        tmf_db()->prepare($sql)->execute($params);
        tmf_json(['ok' => true, 'foto' => $fotoName ? 'uploads/' . $fotoName : null]);
    } catch (Throwable $e) {
        tmf_json(['error' => 'server_error'], 500);
    }
}

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$meinAlter = json_decode($user['altersgruppen'] ?: '[]', true) ?: [];
$meineGalerie = tmf_fotos_list($user['fotos'] ?? '');
$anfrStmt = tmf_db()->prepare("SELECT * FROM anfragen WHERE tm_id = ? ORDER BY created_at DESC LIMIT 50");
$anfrStmt->execute([$user['id']]);
$meineAnfragen = $anfrStmt->fetchAll();
// Profil-Vollständigkeit
$checks = [
    'Profilbild'               => !empty($user['foto']),
    'weitere Bilder'           => !empty($meineGalerie),
    'ausführliche Vorstellung' => mb_strlen((string)($user['persoenlich'] ?? '')) >= 100,
    'Qualifikation'            => !empty($user['qualifikation']),
    'Sprachen'                 => !empty($user['sprachen']),
    '„frei ab"'                => !empty($user['frei_ab']),
    'pädagog. Schwerpunkt'     => !empty($user['konzept']),
    'Telefon'                  => !empty($user['tel']),
    'Pflegeerlaubnis §43'      => !empty($user['erlaubnis']),
];
$prozent = (int)round(count(array_filter($checks)) / count($checks) * 100);
$offen   = array_keys(array_filter($checks, fn($v) => !$v));
$statusLabel = ['pending' => '🕓 Wartet auf Freigabe', 'approved' => '✓ Öffentlich sichtbar', 'rejected' => '✕ Nicht sichtbar'][$user['status']] ?? $user['status'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Mein Profil – Tagesmutter finden</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="styles.css">
<style>
  .k-wrap{max-width:660px;margin:0 auto;padding:2.2rem 1.2rem 4rem}
  .k-card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:2.2rem;box-shadow:var(--shadow)}
  @media(max-width:600px){.k-card{padding:1.4rem}}
  .k-top{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:.4rem}
  .k-top h1{font-size:1.5rem;font-weight:800}
  .k-status{font-size:.8rem;font-weight:800;padding:.25rem .7rem;border-radius:999px;background:var(--sage-bg);color:var(--sage-dark)}
  .k-status.pending{background:var(--amber-bg);color:var(--amber)}
  .k-links{margin-left:auto;display:flex;gap:.9rem;font-size:.85rem;font-weight:700}
  .k-links a{color:var(--muted);text-decoration:none}
  .cur-foto{width:64px;height:64px;border-radius:14px;object-fit:cover}
  .k-progress{background:var(--cream);border:1px solid var(--line);border-radius:14px;padding:.9rem 1.05rem;margin-bottom:1.4rem}
  .kp-top{display:flex;justify-content:space-between;font-weight:800;font-size:.9rem;margin-bottom:.5rem}
  .kp-top b{color:var(--coral)}
  .kp-bar{height:9px;background:#eadfce;border-radius:999px;overflow:hidden}
  .kp-fill{height:100%;background:linear-gradient(90deg,#e0954f,#d9634f);border-radius:999px;transition:width .5s}
  .kp-hint{font-size:.78rem;color:var(--muted);margin-top:.55rem}
</style>
</head>
<body>
<header id="header">
  <div class="header-inner">
    <a class="logo" href="/"><img src="img/logo-tagesmutter.png" alt="Tagesmutter finden" class="logo-img"></a>
    <nav>
      <a href="/profil/<?= $e($user['id']) ?>">Mein öffentliches Profil</a>
      <a href="login.php?logout=1" class="cta">Abmelden</a>
    </nav>
  </div>
</header>

<div class="k-wrap">
  <?php if($meineAnfragen): ?>
  <div class="k-card" style="margin-bottom:1.4rem">
    <h2 style="font-size:1.25rem;font-weight:800;margin-bottom:.9rem">📨 Anfragen von Eltern <span style="color:var(--muted);font-weight:700">(<?= count($meineAnfragen) ?>)</span></h2>
    <?php foreach($meineAnfragen as $a): ?>
    <div style="border:1px solid var(--line);border-radius:14px;padding:.9rem 1rem;margin-bottom:.7rem;background:var(--cream)">
      <div style="font-weight:800"><?= $e($a['name']) ?> <span style="color:var(--muted);font-weight:600;font-size:.85rem">· <?= $e(date('d.m.Y H:i', strtotime((string)$a['created_at']))) ?></span></div>
      <div style="font-size:.92rem;margin:.35rem 0;white-space:pre-wrap"><?= $e($a['nachricht']) ?></div>
      <div style="font-size:.85rem;color:var(--muted)">✉️ <a href="mailto:<?= $e($a['email']) ?>" style="color:var(--coral);font-weight:700"><?= $e($a['email']) ?></a><?= $a['tel'] ? ' · 📞 '.$e($a['tel']) : '' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="k-card">
    <div class="k-top">
      <h1>Mein Profil</h1>
      <span class="k-status" style="background:#eef4fb;color:#4a7fb5">Nr. <?= tmf_usernr($user['nummer']) ?></span>
      <span class="k-status <?= $e($user['status']) ?>"><?= $statusLabel ?></span>
    </div>
    <p class="sub" style="color:var(--ink-soft);margin-bottom:1.1rem">Hallo <?= $e($user['name']) ?>! Hier kannst du deine Angaben jederzeit anpassen. Änderungen sind sofort aktiv.</p>
    <div class="k-progress">
      <div class="kp-top"><span>Profil-Vollständigkeit</span><b><?= $prozent ?> %</b></div>
      <div class="kp-bar"><div class="kp-fill" style="width:<?= $prozent ?>%"></div></div>
      <?php if($offen): ?><p class="kp-hint">Noch offen: <?= $e(implode(', ', $offen)) ?>. Ein volleres Profil wird häufiger angefragt! 💛</p><?php endif; ?>
    </div>
    <div id="msg"></div>
    <form id="form" novalidate>
      <div class="field"><label for="in-name">Name *</label><input type="text" id="in-name" required maxlength="60" value="<?= $e($user['name']) ?>"></div>
      <div class="field" data-bl-feld><label for="in-bundesland">Bundesland *</label><select id="in-bundesland"></select></div>
      <div class="field"><label for="in-ort">Stadt / Gemeinde *</label><select id="in-ort" required></select></div>
      <div class="row">
        <div class="field"><label for="in-plaetze">Freie Plätze *</label>
          <select id="in-plaetze" required>
            <?php for($i=0;$i<=4;$i++): ?><option value="<?= $i ?>" <?= (int)$user['plaetze']===$i?'selected':'' ?>><?= $i===0?'Aktuell keine (Warteliste)':($i.' '.($i===1?'Platz':'Plätze')) ?></option><?php endfor; ?>
          </select>
        </div>
        <div class="field"><label for="in-zeiten">Betreuungszeiten *</label><input type="text" id="in-zeiten" required maxlength="60" value="<?= $e($user['zeiten']) ?>"></div>
      </div>
      <div class="field">
        <label>Altersgruppen *</label>
        <div class="age-boxes">
          <?php foreach(['0–1 Jahr','1–3 Jahre','3+ Jahre'] as $a): ?>
            <label><input type="checkbox" name="alter" value="<?= $e($a) ?>" <?= in_array($a,$meinAlter,true)?'checked':'' ?>> <?= $e($a) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="row">
        <div class="field"><label for="in-qualifikation">Qualifikation</label><input type="text" id="in-qualifikation" maxlength="100" value="<?= $e($user['qualifikation'] ?? '') ?>" placeholder="z. B. Erzieherin"></div>
        <div class="field"><label for="in-frei_ab">Plätze frei ab</label><input type="text" id="in-frei_ab" maxlength="30" value="<?= $e($user['frei_ab'] ?? '') ?>" placeholder="z. B. sofort"></div>
      </div>
      <div class="row">
        <div class="field"><label for="in-sprachen">Sprachen</label><input type="text" id="in-sprachen" maxlength="100" value="<?= $e($user['sprachen'] ?? '') ?>" placeholder="z. B. Deutsch, Türkisch"></div>
        <div class="field"><label for="in-ernaehrung">Ernährung</label><input type="text" id="in-ernaehrung" maxlength="100" value="<?= $e($user['ernaehrung'] ?? '') ?>" placeholder="z. B. frisch gekocht"></div>
      </div>
      <div class="row">
        <div class="field"><label for="in-haustiere">Haustiere</label><input type="text" id="in-haustiere" maxlength="60" value="<?= $e($user['haustiere'] ?? '') ?>" placeholder="z. B. Hund, keine"></div>
        <div class="field"><label style="display:block;margin-bottom:.35rem">&nbsp;</label><label class="toggle" style="display:inline-flex"><input type="checkbox" id="in-nichtraucher" <?= !empty($user['nichtraucher'])?'checked':'' ?>> Nichtraucher-Haushalt</label></div>
      </div>
      <div class="field">
        <label for="in-konzept">Pädagogischer Schwerpunkt <span class="opt">(max. 400 Zeichen)</span></label>
        <textarea id="in-konzept" rows="2" maxlength="400" placeholder="z. B. Natur &amp; Bewegung, feste Rituale …"><?= $e($user['konzept'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label>Betreuungs-Extras</label>
        <div class="age-boxes" id="extras-boxes"></div>
      </div>
      <div class="field">
        <label for="in-foto">Profilbild</label>
        <div class="photo-upload">
          <div class="photo-preview" id="foto-preview"><?php if($user['foto']): ?><img src="uploads/<?= $e($user['foto']) ?>" alt=""><?php else: ?>📷<?php endif; ?></div>
          <div class="photo-meta">
            <input type="file" id="in-foto" accept="image/*">
            <p class="opt-hint">Dein Hauptbild (Übersicht + Profil). Neues ersetzt das alte, leer = bleibt.</p>
            <?php if($user['foto']): ?><label class="toggle" style="display:inline-flex;margin-top:.5rem"><input type="checkbox" id="in-foto-weg"> Profilbild ganz entfernen</label><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="field">
        <label for="in-galerie">Weitere Bilder <span class="opt">(bis zu 5 – Bilder-Wechsel auf deinem Profil)</span></label>
        <div class="galerie-preview" id="galerie-preview">
          <?php foreach($meineGalerie as $g): ?>
          <div class="g-thumb" data-name="<?= $e($g) ?>"><img src="uploads/<?= $e($g) ?>" alt=""><button type="button" title="Entfernen">✕</button></div>
          <?php endforeach; ?>
        </div>
        <input type="file" id="in-galerie" accept="image/*" multiple style="margin-top:.6rem">
        <p class="opt-hint">Frei wählbar, jederzeit änderbar. Mehrere auf einmal möglich · ✕ entfernt ein Bild.</p>
      </div>
      <div class="field">
        <label for="in-text">Persönliche Vorstellung *</label>
        <textarea id="in-text" required rows="6" maxlength="1500"><?= $e($user['persoenlich']) ?></textarea>
      </div>
      <div class="row">
        <div class="field"><label for="in-email">E-Mail (Login) *</label><input type="email" id="in-email" required maxlength="100" value="<?= $e($user['email']) ?>" autocomplete="email"></div>
        <div class="field"><label for="in-tel">Telefon</label><input type="tel" id="in-tel" maxlength="30" value="<?= $e($user['tel']) ?>"></div>
      </div>
      <div class="field">
        <label for="in-pass">Neues Passwort <span class="opt">(nur wenn du es ändern willst, mind. 8 Zeichen)</span></label>
        <input type="password" id="in-pass" minlength="8" placeholder="leer lassen = unverändert" autocomplete="new-password" style="width:100%;border:1.5px solid var(--line);border-radius:13px;padding:.7rem .95rem;font-size:.95rem;font-family:inherit;background:var(--cream)">
      </div>
      <div class="field">
        <label class="toggle" style="display:inline-flex"><input type="checkbox" id="in-erlaubnis" <?= $user['erlaubnis']?'checked':'' ?>> Ich habe eine Pflegeerlaubnis nach §&nbsp;43 SGB&nbsp;VIII</label>
      </div>
      <div class="submit-row"><button type="submit" class="btn btn-coral">Änderungen speichern</button></div>
    </form>
    <div style="margin-top:2rem;padding-top:1.4rem;border-top:1px solid var(--line)">
      <button type="button" id="del-btn" class="btn" style="background:none;border:1.5px solid #e0b4b4;color:#c0392b">Konto &amp; Profil löschen</button>
      <p class="opt-hint">Löscht dein Profil, deine Bilder und Anfragen unwiderruflich (DSGVO).</p>
    </div>
  </div>
</div>

<script src="data.js"></script>
<script>
// Stadt-/Regionsauswahl, gespeicherte Werte vorgewählt (Bundesland im BW-Modus ausgeblendet)
initStadtauswahl(document.getElementById("in-bundesland"), document.getElementById("in-ort"), <?= json_encode($user['bundesland'] ?: 'Baden-Württemberg', JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($user['ort'], JSON_UNESCAPED_UNICODE) ?>, false);
const meineExtras = <?= json_encode(json_decode($user['extras'] ?? '[]', true) ?: [], JSON_UNESCAPED_UNICODE) ?>;
document.getElementById("extras-boxes").innerHTML = EXTRAS.map(x => `<label><input type="checkbox" name="extras" value="${x}" ${meineExtras.includes(x)?"checked":""}> ${x}</label>`).join("");

let fotoBlob = null;
const fotoInput = document.getElementById("in-foto");
const fotoPreview = document.getElementById("foto-preview");
function verkleinereFoto(file, maxSeite = 512){
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const f = Math.min(1, maxSeite / Math.max(img.width, img.height));
      const c = document.createElement("canvas");
      c.width = Math.round(img.width * f); c.height = Math.round(img.height * f);
      c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(img.src);
      c.toBlob(b => b ? resolve(b) : reject(new Error("Blob")), "image/jpeg", .85);
    };
    img.onerror = () => reject(new Error("Bild nicht lesbar"));
    img.src = URL.createObjectURL(file);
  });
}
fotoInput.addEventListener("change", async () => {
  const file = fotoInput.files[0]; if(!file) return;
  if(!file.type.startsWith("image/")){ alert("Bitte eine Bilddatei wählen."); fotoInput.value=""; return; }
  try{ fotoBlob = await verkleinereFoto(file); fotoPreview.innerHTML = `<img src="${URL.createObjectURL(fotoBlob)}" alt="">`; }
  catch(e){ alert("Bild konnte nicht gelesen werden."); }
});

// Galerie: vorhandene Bilder (data-name bleibt = behalten) + neu hinzugefügte Blobs
const galeriePreview = document.getElementById("galerie-preview");
const galerieInput = document.getElementById("in-galerie");
const neueBlobs = new Map(); // id → Blob
let neuId = 0;
const galerieAnzahl = () => galeriePreview.querySelectorAll(".g-thumb").length;
galerieInput.addEventListener("change", async () => {
  for(const file of [...galerieInput.files]){
    if(galerieAnzahl() >= 5){ alert("Maximal 5 weitere Bilder möglich."); break; }
    if(!file.type.startsWith("image/")) continue;
    try{
      const blob = await verkleinereFoto(file, 900);
      const id = "n" + (neuId++);
      neueBlobs.set(id, blob);
      const div = document.createElement("div");
      div.className = "g-thumb"; div.dataset.neu = id;
      div.innerHTML = `<img src="${URL.createObjectURL(blob)}" alt=""><button type="button" title="Entfernen">✕</button>`;
      galeriePreview.appendChild(div);
    }catch(e){}
  }
  galerieInput.value = "";
});
galeriePreview.addEventListener("click", ev => {
  const btn = ev.target.closest("button"); if(!btn) return;
  const thumb = btn.closest(".g-thumb");
  if(thumb.dataset.neu) neueBlobs.delete(thumb.dataset.neu);
  thumb.remove();
});

document.getElementById("form").addEventListener("submit", async ev => {
  ev.preventDefault();
  const f = ev.target;
  if(!f.reportValidity()) return;
  const alter = [...f.querySelectorAll('input[name="alter"]:checked')].map(c => c.value);
  if(alter.length === 0){ alert("Bitte mindestens eine Altersgruppe auswählen."); return; }

  const fd = new FormData();
  fd.append("name", document.getElementById("in-name").value.trim());
  fd.append("bundesland", regionBundesland(document.getElementById("in-bundesland")));
  fd.append("ort", document.getElementById("in-ort").value);
  galeriePreview.querySelectorAll(".g-thumb[data-name]").forEach(t => fd.append("behalten[]", t.dataset.name));
  neueBlobs.forEach(blob => fd.append("galerie[]", blob, "bild.jpg"));
  fd.append("plaetze", document.getElementById("in-plaetze").value);
  fd.append("zeiten", document.getElementById("in-zeiten").value.trim());
  alter.forEach(a => fd.append("alter[]", a));
  fd.append("persoenlich", document.getElementById("in-text").value.trim());
  fd.append("email", document.getElementById("in-email").value.trim());
  fd.append("tel", document.getElementById("in-tel").value.trim());
  const pw = document.getElementById("in-pass").value;
  if(pw) fd.append("passwort", pw);
  if(document.getElementById("in-erlaubnis").checked) fd.append("erlaubnis", "1");
  ["qualifikation","sprachen","frei_ab","ernaehrung","haustiere","konzept"].forEach(k => fd.append(k, document.getElementById("in-"+k).value.trim()));
  if(document.getElementById("in-nichtraucher").checked) fd.append("nichtraucher", "1");
  [...document.querySelectorAll('input[name="extras"]:checked')].forEach(c => fd.append("extras[]", c.value));
  const fotoWeg = document.getElementById("in-foto-weg");
  if(fotoWeg && fotoWeg.checked) fd.append("foto_entfernen", "1");
  if(fotoBlob) fd.append("foto", fotoBlob, "foto.jpg");

  const btn = f.querySelector('button[type="submit"]');
  btn.disabled = true; btn.classList.add("loading");
  const msg = document.getElementById("msg");
  try{
    const res = await fetch("mein-konto.php", {method:"POST", body:fd});
    const data = await res.json().catch(() => ({}));
    if(!res.ok || !data.ok) throw new Error(data.error || "Speichern fehlgeschlagen");
    msg.innerHTML = '<div class="auth-ok">✅ Gespeichert! Deine Änderungen sind aktiv.</div>';
    document.getElementById("in-pass").value = "";
    msg.scrollIntoView({behavior:"smooth", block:"center"});
  }catch(err){
    msg.innerHTML = `<div class="auth-err">${err.message.replace(/</g,"&lt;")}</div>`;
  }finally{
    btn.disabled = false; btn.classList.remove("loading");
  }
});

// Konto löschen
document.getElementById("del-btn").addEventListener("click", async () => {
  if(!confirm("Wirklich dein komplettes Profil und alle zugehörigen Daten unwiderruflich löschen?")) return;
  const fd = new FormData(); fd.append("aktion", "loeschen");
  try{
    const res = await fetch("mein-konto.php", {method:"POST", body:fd});
    const data = await res.json().catch(() => ({}));
    if(data.geloescht){ alert("Dein Konto wurde gelöscht."); location.href = "/"; }
    else throw new Error();
  }catch(e){ alert("Löschen fehlgeschlagen – bitte später erneut versuchen."); }
});

// Zeichenzähler für die Vorstellungs-Textarea
(function(){
  const ta = document.getElementById("in-text");
  if(!ta || ta.maxLength <= 0) return;
  const cc = document.createElement("div");
  cc.className = "char-count";
  ta.insertAdjacentElement("afterend", cc);
  const upd = () => { const n = ta.value.length; cc.textContent = n + " / " + ta.maxLength; cc.classList.toggle("warn", n > ta.maxLength * 0.92); };
  ta.addEventListener("input", upd); upd();
})();
</script>
</body>
</html>

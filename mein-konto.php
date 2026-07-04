<?php
declare(strict_types=1);
require __DIR__ . '/api/auth.php';
$user = tmf_require_login();

// ---------- Speichern (POST via fetch, FormData → JSON) ----------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $clean = static fn(string $s, int $max): string =>
        mb_substr(trim(str_replace(["\r", "\n"], ' ', $s)), 0, $max);

    $name        = $clean($_POST['name'] ?? '', 80);
    $ort         = $clean($_POST['ort'] ?? '', 80);
    $plaetze     = max(0, min(9, (int)($_POST['plaetze'] ?? 0)));
    $zeiten      = $clean($_POST['zeiten'] ?? '', 120);
    $email       = mb_strtolower($clean($_POST['email'] ?? '', 120));
    $tel         = $clean($_POST['tel'] ?? '', 40);
    $erlaubnis   = !empty($_POST['erlaubnis']) ? 1 : 0;
    $persoenlich = mb_substr(trim((string)($_POST['persoenlich'] ?? '')), 0, 1500);
    $pass        = (string)($_POST['passwort'] ?? '');

    $alter = $_POST['alter'] ?? [];
    if (is_string($alter)) $alter = array_filter(array_map('trim', explode(',', $alter)));
    $erlaubteAlter = ['0–1 Jahr', '1–3 Jahre', '3+ Jahre'];
    $alter = array_values(array_intersect($erlaubteAlter, (array)$alter));

    $fehler = [];
    if ($name === '')        $fehler[] = 'Name fehlt';
    if ($ort === '')         $fehler[] = 'Stadtteil fehlt';
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

    $sql = "UPDATE tagesmuetter SET name=?, ort=?, plaetze=?, zeiten=?, altersgruppen=?, persoenlich=?, email=?, tel=?, erlaubnis=?, updated_at=CURRENT_TIMESTAMP";
    $params = [$name, $ort, $plaetze, $zeiten, json_encode($alter, JSON_UNESCAPED_UNICODE), $persoenlich, $email, $tel, $erlaubnis];
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
</style>
</head>
<body>
<header id="header">
  <div class="header-inner">
    <a class="logo" href="index.html"><span class="mark">🧸</span><span class="word">Tagesmutter finden<small>Balingen</small></span></a>
    <nav>
      <a href="profil.html?id=<?= $e($user['id']) ?>">Mein öffentliches Profil</a>
      <a href="login.php?logout=1" class="cta">Abmelden</a>
    </nav>
  </div>
</header>

<div class="k-wrap">
  <div class="k-card">
    <div class="k-top">
      <h1>Mein Profil</h1>
      <span class="k-status <?= $e($user['status']) ?>"><?= $statusLabel ?></span>
    </div>
    <p class="sub" style="color:var(--ink-soft);margin-bottom:1.4rem">Hallo <?= $e($user['name']) ?>! Hier kannst du deine Angaben jederzeit anpassen. Änderungen sind sofort aktiv.</p>
    <div id="msg"></div>
    <form id="form" novalidate>
      <div class="row">
        <div class="field"><label for="in-name">Name *</label><input type="text" id="in-name" required maxlength="60" value="<?= $e($user['name']) ?>"></div>
        <div class="field"><label for="in-ort">Stadtteil *</label><select id="in-ort" required></select></div>
      </div>
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
      <div class="field">
        <label for="in-foto">Foto</label>
        <div class="photo-upload">
          <div class="photo-preview" id="foto-preview"><?php if($user['foto']): ?><img src="uploads/<?= $e($user['foto']) ?>" alt=""><?php else: ?>📷<?php endif; ?></div>
          <div class="photo-meta">
            <input type="file" id="in-foto" accept="image/*">
            <p class="opt-hint">Neues Foto ersetzt das alte. Leer lassen = Foto bleibt.</p>
            <?php if($user['foto']): ?><label class="toggle" style="display:inline-flex;margin-top:.5rem"><input type="checkbox" id="in-foto-weg"> Foto ganz entfernen</label><?php endif; ?>
          </div>
        </div>
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
  </div>
</div>

<script src="data.js"></script>
<script>
const AKTUELL_ORT = <?= json_encode($user['ort'], JSON_UNESCAPED_UNICODE) ?>;
const inOrt = document.getElementById("in-ort");
STADTTEILE.forEach(s => inOrt.insertAdjacentHTML("beforeend", `<option ${s===AKTUELL_ORT?"selected":""}>${s}</option>`));

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

document.getElementById("form").addEventListener("submit", async ev => {
  ev.preventDefault();
  const f = ev.target;
  if(!f.reportValidity()) return;
  const alter = [...f.querySelectorAll('input[name="alter"]:checked')].map(c => c.value);
  if(alter.length === 0){ alert("Bitte mindestens eine Altersgruppe auswählen."); return; }

  const fd = new FormData();
  fd.append("name", document.getElementById("in-name").value.trim());
  fd.append("ort", document.getElementById("in-ort").value);
  fd.append("plaetze", document.getElementById("in-plaetze").value);
  fd.append("zeiten", document.getElementById("in-zeiten").value.trim());
  alter.forEach(a => fd.append("alter[]", a));
  fd.append("persoenlich", document.getElementById("in-text").value.trim());
  fd.append("email", document.getElementById("in-email").value.trim());
  fd.append("tel", document.getElementById("in-tel").value.trim());
  const pw = document.getElementById("in-pass").value;
  if(pw) fd.append("passwort", pw);
  if(document.getElementById("in-erlaubnis").checked) fd.append("erlaubnis", "1");
  const fotoWeg = document.getElementById("in-foto-weg");
  if(fotoWeg && fotoWeg.checked) fd.append("foto_entfernen", "1");
  if(fotoBlob) fd.append("foto", fotoBlob, "foto.jpg");

  const btn = f.querySelector('button[type="submit"]');
  const label = btn.textContent; btn.disabled = true; btn.textContent = "Speichere …";
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
    btn.disabled = false; btn.textContent = label;
  }
});
</script>
</body>
</html>

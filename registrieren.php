<?php
declare(strict_types=1);
require __DIR__ . '/api/auth.php';
if (tmf_current_user()) { header('Location: mein-konto.php'); exit; }
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Als Tagesmutter registrieren – Tagesmutter finden</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="styles.css">
<style>
  .reg-wrap{max-width:660px;margin:0 auto;padding:2.2rem 1.2rem 4rem}
  .reg-card{background:#fff;border:1px solid var(--line);border-radius:22px;padding:2.2rem;box-shadow:var(--shadow)}
  @media(max-width:600px){.reg-card{padding:1.4rem}}
  .reg-card h1{font-size:1.6rem;font-weight:800;letter-spacing:-.02em}
  .reg-card .sub{color:var(--ink-soft);margin:.4rem 0 1.6rem}
</style>
</head>
<body>
<header id="header">
  <div class="header-inner">
    <a class="logo" href="index.html"><span class="mark">🧸</span><span class="word">Tagesmutter finden<small>Kindertagespflege</small></span></a>
    <nav><a href="login.php" class="cta">Anmelden</a></nav>
  </div>
</header>

<div class="reg-wrap">
  <div class="reg-card">
    <h1>Als Tagesmutter eintragen</h1>
    <p class="sub">Kostenlos · dein Profil wird nach einer kurzen Prüfung freigeschaltet. Danach kannst du es jederzeit selbst bearbeiten.</p>
    <div id="msg"></div>
    <form id="form" novalidate>
      <div class="field"><label for="in-name">Name *</label><input type="text" id="in-name" required maxlength="60" placeholder="z. B. Maria S."></div>
      <div class="row">
        <div class="field"><label for="in-bundesland">Bundesland *</label><select id="in-bundesland" required></select></div>
        <div class="field"><label for="in-ort">Stadt / Gemeinde *</label><select id="in-ort" required></select></div>
      </div>
      <div class="row">
        <div class="field"><label for="in-plaetze">Freie Plätze *</label>
          <select id="in-plaetze" required>
            <option value="0">Aktuell keine (Warteliste)</option>
            <option value="1">1 Platz</option>
            <option value="2" selected>2 Plätze</option>
            <option value="3">3 Plätze</option>
            <option value="4">4 Plätze</option>
          </select>
        </div>
        <div class="field"><label for="in-zeiten">Betreuungszeiten *</label><input type="text" id="in-zeiten" required maxlength="60" placeholder="z. B. Mo–Fr 7:30–16:00"></div>
      </div>
      <div class="field">
        <label>Altersgruppen * <span class="opt">(mindestens eine)</span></label>
        <div class="age-boxes">
          <label><input type="checkbox" name="alter" value="0–1 Jahr"> 0–1 Jahr</label>
          <label><input type="checkbox" name="alter" value="1–3 Jahre" checked> 1–3 Jahre</label>
          <label><input type="checkbox" name="alter" value="3+ Jahre"> 3+ Jahre</label>
        </div>
      </div>
      <div class="field">
        <label for="in-foto">Profilbild <span class="opt">(optional)</span></label>
        <div class="photo-upload">
          <div class="photo-preview" id="foto-preview">📷</div>
          <div class="photo-meta">
            <input type="file" id="in-foto" accept="image/*">
            <p class="opt-hint">Dein Hauptbild – erscheint in der Übersicht und als erstes auf deinem Profil.</p>
            <a href="#" id="foto-entfernen" hidden>✕ Entfernen</a>
          </div>
        </div>
      </div>
      <div class="field">
        <label for="in-galerie">Weitere Bilder <span class="opt">(optional, bis zu 5 – frei wählbar)</span></label>
        <input type="file" id="in-galerie" accept="image/*" multiple>
        <p class="opt-hint">Diese Bilder wechseln sich auf deinem Profil ab. Mehrere auf einmal auswählbar.</p>
        <div class="galerie-preview" id="galerie-preview"></div>
      </div>
      <div class="field">
        <label for="in-text">Persönliche Vorstellung * <span class="opt">(max. 1.500 Zeichen)</span></label>
        <textarea id="in-text" required rows="6" maxlength="1500" placeholder="Stell dich den Eltern vor: Wer bist du? Wie sieht ein Tag bei dir aus? Was ist dir wichtig? …"></textarea>
      </div>

      <hr style="border:none;border-top:1px solid var(--line);margin:1.4rem 0">
      <div class="row">
        <div class="field"><label for="in-email">E-Mail * <span class="opt">(dein Login)</span></label><input type="email" id="in-email" required maxlength="100" placeholder="name@beispiel.de" autocomplete="email"></div>
        <div class="field"><label for="in-tel">Telefon <span class="opt">(optional)</span></label><input type="tel" id="in-tel" maxlength="30" placeholder="07433 …"></div>
      </div>
      <div class="field">
        <label for="in-pass">Passwort * <span class="opt">(mind. 8 Zeichen — damit meldest du dich später an)</span></label>
        <input type="password" id="in-pass" required minlength="8" placeholder="Passwort wählen" autocomplete="new-password" style="width:100%;border:1.5px solid var(--line);border-radius:13px;padding:.7rem .95rem;font-size:.95rem;font-family:inherit;background:var(--cream)">
      </div>
      <div class="field">
        <label class="toggle" style="display:inline-flex"><input type="checkbox" id="in-erlaubnis"> Ich habe eine Pflegeerlaubnis nach §&nbsp;43 SGB&nbsp;VIII</label>
      </div>
      <label class="consent">
        <input type="checkbox" id="in-consent" required>
        <span><b>Einwilligung (erforderlich):</b> Ich willige ein, dass die oben angegebenen Daten öffentlich auf diesem Portal angezeigt werden. Ich kann meinen Eintrag jederzeit selbst ändern oder löschen.</span>
      </label>
      <div class="submit-row"><button type="submit" class="btn btn-coral">Registrieren</button></div>
      <p class="form-note">Nach dem Absenden wird dein Profil geprüft und dann freigeschaltet. Du bist sofort eingeloggt und kannst dein Profil bearbeiten.</p>
    </form>
    <div class="links" style="text-align:center;margin-top:1.2rem;font-size:.9rem;color:var(--muted)">
      Schon registriert? <a href="login.php" style="color:var(--coral);font-weight:800;text-decoration:none">Hier anmelden</a>
    </div>
  </div>
</div>

<script src="data.js"></script>
<script>
// Bundesland → Stadt (abhängige Dropdowns), Baden-Württemberg vorgewählt
initOrtsauswahl(document.getElementById("in-bundesland"), document.getElementById("in-ort"), "Baden-Württemberg", null, false);

// Foto-Upload (clientseitig auf 512px verkleinert → Blob)
let fotoBlob = null;
const fotoInput = document.getElementById("in-foto");
const fotoPreview = document.getElementById("foto-preview");
const fotoEntfernen = document.getElementById("foto-entfernen");
function verkleinereFoto(file, maxSeite = 512){
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const f = Math.min(1, maxSeite / Math.max(img.width, img.height));
      const c = document.createElement("canvas");
      c.width = Math.round(img.width * f); c.height = Math.round(img.height * f);
      c.getContext("2d").drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(img.src);
      c.toBlob(b => b ? resolve(b) : reject(new Error("Blob fehlgeschlagen")), "image/jpeg", .85);
    };
    img.onerror = () => { URL.revokeObjectURL(img.src); reject(new Error("Bild nicht lesbar")); };
    img.src = URL.createObjectURL(file);
  });
}
function fotoZuruecksetzen(){ fotoBlob = null; fotoInput.value = ""; fotoPreview.textContent = "📷"; fotoEntfernen.hidden = true; }
fotoInput.addEventListener("change", async () => {
  const file = fotoInput.files[0]; if(!file) return;
  if(!file.type.startsWith("image/")){ alert("Bitte eine Bilddatei wählen."); fotoZuruecksetzen(); return; }
  try{ fotoBlob = await verkleinereFoto(file); fotoPreview.innerHTML = `<img src="${URL.createObjectURL(fotoBlob)}" alt="">`; fotoEntfernen.hidden = false; }
  catch(err){ alert("Dieses Bild konnte nicht gelesen werden."); fotoZuruecksetzen(); }
});
fotoEntfernen.addEventListener("click", ev => { ev.preventDefault(); fotoZuruecksetzen(); });

// Galerie: bis zu 5 frei wählbare Bilder (clientseitig auf 900px verkleinert)
let galerieBlobs = [];
const galerieInput = document.getElementById("in-galerie");
const galeriePreview = document.getElementById("galerie-preview");
galerieInput.addEventListener("change", async () => {
  for(const file of [...galerieInput.files]){
    if(galerieBlobs.length >= 5){ alert("Maximal 5 weitere Bilder möglich."); break; }
    if(!file.type.startsWith("image/")) continue;
    try{ galerieBlobs.push(await verkleinereFoto(file, 900)); }catch(e){}
  }
  galerieInput.value = "";
  zeigeGalerie();
});
function zeigeGalerie(){
  galeriePreview.innerHTML = galerieBlobs.map((b,i) =>
    `<div class="g-thumb"><img src="${URL.createObjectURL(b)}" alt=""><button type="button" data-i="${i}" title="Entfernen">✕</button></div>`
  ).join("");
}
galeriePreview.addEventListener("click", ev => {
  const btn = ev.target.closest("button[data-i]");
  if(!btn) return;
  galerieBlobs.splice(+btn.dataset.i, 1);
  zeigeGalerie();
});

// Absenden → Registrierung
document.getElementById("form").addEventListener("submit", async ev => {
  ev.preventDefault();
  const f = ev.target;
  if(!f.reportValidity()) return;
  const alter = [...f.querySelectorAll('input[name="alter"]:checked')].map(c => c.value);
  if(alter.length === 0){ alert("Bitte mindestens eine Altersgruppe auswählen."); return; }
  if(!document.getElementById("in-consent").checked){ alert("Ohne Einwilligung können wir dich nicht eintragen."); return; }

  const fd = new FormData();
  fd.append("name", document.getElementById("in-name").value.trim());
  fd.append("bundesland", document.getElementById("in-bundesland").value);
  fd.append("ort", document.getElementById("in-ort").value);
  fd.append("plaetze", document.getElementById("in-plaetze").value);
  fd.append("zeiten", document.getElementById("in-zeiten").value.trim());
  alter.forEach(a => fd.append("alter[]", a));
  fd.append("persoenlich", document.getElementById("in-text").value.trim());
  fd.append("email", document.getElementById("in-email").value.trim());
  fd.append("tel", document.getElementById("in-tel").value.trim());
  fd.append("passwort", document.getElementById("in-pass").value);
  if(document.getElementById("in-erlaubnis").checked) fd.append("erlaubnis", "1");
  fd.append("consent", "1");
  if(fotoBlob) fd.append("foto", fotoBlob, "foto.jpg");

  const btn = f.querySelector('button[type="submit"]');
  const label = btn.textContent; btn.disabled = true; btn.textContent = "Wird angelegt …";
  try{
    const res = await fetch("api/register.php", {method:"POST", body:fd});
    const data = await res.json().catch(() => ({}));
    if(!res.ok || !data.ok) throw new Error(data.error || "Registrierung fehlgeschlagen");
    window.location.href = "mein-konto.php?neu=1";
  }catch(err){
    document.getElementById("msg").innerHTML = `<div class="auth-err">${err.message.replace(/</g,"&lt;")}</div>`;
    document.getElementById("msg").scrollIntoView({behavior:"smooth", block:"center"});
    btn.disabled = false; btn.textContent = label;
  }
});
</script>
</body>
</html>

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
  /* Wizard-Fortschritt */
  .wiz-progress{display:flex;gap:.5rem;margin-bottom:1.8rem}
  .wiz-step-ind{flex:1;display:flex;align-items:center;justify-content:center;gap:.5rem;font-size:.84rem;font-weight:800;color:var(--muted);cursor:pointer;padding:.5rem;border-radius:12px;background:var(--cream);border:1.5px solid var(--line)}
  .wiz-step-ind .wsi-num{width:26px;height:26px;border-radius:50%;display:grid;place-items:center;background:#eee1d3;color:var(--muted);font-size:.9rem;flex-shrink:0;transition:.2s}
  .wiz-step-ind.active{border-color:var(--coral)}
  .wiz-step-ind.active .wsi-num{background:var(--grad-coral);color:#fff}
  .wiz-step-ind.done .wsi-num{background:var(--sage);color:#fff}
  .wiz-step-ind.active .wsi-lbl,.wiz-step-ind.done .wsi-lbl{color:var(--ink)}
  .wiz-step{border:none;padding:0;margin:0;min-width:0}
  .wiz-nav{display:flex;gap:.6rem;align-items:center;margin-top:1.6rem;flex-wrap:wrap}
  .wiz-spacer{flex:1}
  @media(max-width:520px){.wsi-lbl{display:none}}
  /* Live-Vorschau */
  .vorschau-card{background:var(--card);border-radius:20px;padding:1.8rem;max-width:520px;width:94%;max-height:88vh;overflow-y:auto;cursor:default;box-shadow:var(--shadow-lg)}
  .vs-head{display:flex;gap:1.1rem;align-items:center}
  .vs-head .p-photo{width:84px;height:84px;border-radius:20px}
  .vs-head .p-photo .ini{font-size:2rem}
  .vs-name{font-size:1.4rem;font-weight:800;letter-spacing:-.01em}
  .vs-ort{color:var(--muted);font-weight:700;font-size:.92rem}
  .vs-text{white-space:pre-line;color:var(--ink-soft);margin-top:.8rem;font-size:.96rem}
  .vs-note{text-align:center;color:var(--muted);font-size:.78rem;margin-top:1.3rem;font-weight:700}
</style>
</head>
<body>
<header id="header">
  <div class="header-inner">
    <a class="logo" href="/"><img src="img/logo-tagesmutter.png" alt="Tagesmutter finden" class="logo-img"></a>
    <nav><a href="login.php" class="cta">Anmelden</a></nav>
  </div>
</header>

<div class="reg-wrap">
  <div class="reg-card">
    <h1>Als Tagesmutter eintragen</h1>
    <p class="sub">Kostenlos · dein Profil wird nach einer kurzen Prüfung freigeschaltet. Danach kannst du es jederzeit selbst bearbeiten.</p>
    <div id="msg"></div>
    <div class="wiz-progress" id="wiz-progress">
      <div class="wiz-step-ind active" data-s="1"><span class="wsi-num">1</span><span class="wsi-lbl">Betreuung</span></div>
      <div class="wiz-step-ind" data-s="2"><span class="wsi-num">2</span><span class="wsi-lbl">Dein Profil</span></div>
      <div class="wiz-step-ind" data-s="3"><span class="wsi-num">3</span><span class="wsi-lbl">Zugang</span></div>
    </div>
    <form id="form" novalidate>
      <fieldset class="wiz-step" data-step="1">
        <div class="field"><label for="in-name">Name *</label><input type="text" id="in-name" required maxlength="60" placeholder="z. B. Maria S."></div>
        <div class="field" data-bl-feld><label for="in-bundesland">Bundesland *</label><select id="in-bundesland"></select></div>
        <div class="field"><label for="in-ort">Stadt / Gemeinde *</label><select id="in-ort" required></select></div>
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
      </fieldset>

      <fieldset class="wiz-step" data-step="2" hidden>
        <div class="field">
          <label for="in-text">Persönliche Vorstellung * <span class="opt">(max. 1.500 Zeichen)</span></label>
          <textarea id="in-text" required rows="6" maxlength="1500" placeholder="Stell dich den Eltern vor: Wer bist du? Wie sieht ein Tag bei dir aus? Was ist dir wichtig? …"></textarea>
        </div>
        <div class="row">
          <div class="field"><label for="in-qualifikation">Qualifikation <span class="opt">(optional)</span></label><input type="text" id="in-qualifikation" maxlength="100" placeholder="z. B. Erzieherin, 160h-Qualifizierung"></div>
          <div class="field"><label for="in-frei_ab">Plätze frei ab <span class="opt">(optional)</span></label><input type="text" id="in-frei_ab" maxlength="30" placeholder="z. B. sofort, ab 09/2026"></div>
        </div>
        <div class="row">
          <div class="field"><label for="in-sprachen">Sprachen <span class="opt">(optional)</span></label><input type="text" id="in-sprachen" maxlength="100" placeholder="z. B. Deutsch, Türkisch"></div>
          <div class="field"><label for="in-ernaehrung">Ernährung <span class="opt">(optional)</span></label><input type="text" id="in-ernaehrung" maxlength="100" placeholder="z. B. frisch gekocht, vegetarisch"></div>
        </div>
        <div class="row">
          <div class="field"><label for="in-haustiere">Haustiere <span class="opt">(optional)</span></label><input type="text" id="in-haustiere" maxlength="60" placeholder="z. B. Hund, keine"></div>
          <div class="field"><label style="display:block;margin-bottom:.35rem">&nbsp;</label><label class="toggle" style="display:inline-flex"><input type="checkbox" id="in-nichtraucher"> Nichtraucher-Haushalt</label></div>
        </div>
        <div class="field">
          <label for="in-konzept">Pädagogischer Schwerpunkt <span class="opt">(optional, max. 400 Zeichen)</span></label>
          <textarea id="in-konzept" rows="2" maxlength="400" placeholder="z. B. Natur & Bewegung, feste Rituale, viel Freispiel …"></textarea>
        </div>
        <div class="field">
          <label>Betreuungs-Extras <span class="opt">(optional, Mehrfachauswahl)</span></label>
          <div class="age-boxes" id="extras-boxes"></div>
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
      </fieldset>

      <fieldset class="wiz-step" data-step="3" hidden>
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
          <span><b>Zustimmung (erforderlich):</b> Ich habe die <a href="agb.html" target="_blank" rel="noopener">Nutzungsbedingungen</a> gelesen und akzeptiere sie. Ich willige ein, dass meine Angaben und hochgeladenen Fotos gemäß <a href="datenschutz.html" target="_blank" rel="noopener">Datenschutzerklärung</a> gespeichert, verarbeitet und öffentlich auf diesem Portal angezeigt werden. Ich bestätige, dass meine Angaben korrekt sind und ich an allen Fotos die nötigen Rechte sowie die Einwilligung abgebildeter Personen (bei Kindern der Erziehungsberechtigten) besitze. Ich kann mein Profil jederzeit selbst ändern oder löschen.</span>
        </label>
      </fieldset>

      <div class="wiz-nav">
        <button type="button" class="btn btn-ghost" id="wiz-back" hidden>← Zurück</button>
        <span class="wiz-spacer"></span>
        <button type="button" class="btn btn-ghost" id="wiz-preview" hidden>👁 Vorschau</button>
        <button type="button" class="btn btn-coral" id="wiz-next">Weiter →</button>
        <button type="submit" class="btn btn-coral" id="wiz-submit" hidden>✓ Registrieren</button>
      </div>
      <p class="form-note">Nach dem Absenden wird dein Profil geprüft und dann freigeschaltet. Du bist sofort eingeloggt und kannst dein Profil bearbeiten.</p>
    </form>
    <div class="links" style="text-align:center;margin-top:1.2rem;font-size:.9rem;color:var(--muted)">
      Schon registriert? <a href="login.php" style="color:var(--coral);font-weight:800;text-decoration:none">Hier anmelden</a>
    </div>
  </div>
</div>

<script src="data.js"></script>
<script>
// Stadt-/Regionsauswahl (blendet Bundesland im BW-Modus aus, sonst volle Kaskade)
initStadtauswahl(document.getElementById("in-bundesland"), document.getElementById("in-ort"), "", "", false);
document.getElementById("extras-boxes").innerHTML = EXTRAS.map(x => `<label><input type="checkbox" name="extras" value="${x}"> ${x}</label>`).join("");

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
  fd.append("bundesland", regionBundesland(document.getElementById("in-bundesland")));
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
  ["qualifikation","sprachen","frei_ab","ernaehrung","haustiere","konzept"].forEach(k => fd.append(k, document.getElementById("in-"+k).value.trim()));
  if(document.getElementById("in-nichtraucher").checked) fd.append("nichtraucher", "1");
  [...document.querySelectorAll('input[name="extras"]:checked')].forEach(c => fd.append("extras[]", c.value));
  if(fotoBlob) fd.append("foto", fotoBlob, "foto.jpg");
  galerieBlobs.forEach((b,i) => fd.append("galerie[]", b, "bild"+i+".jpg"));

  const btn = f.querySelector('button[type="submit"]');
  btn.disabled = true; btn.classList.add("loading");
  try{
    const res = await fetch("api/register.php", {method:"POST", body:fd});
    const data = await res.json().catch(() => ({}));
    if(!res.ok || !data.ok) throw new Error(data.error || "Registrierung fehlgeschlagen");
    window.location.href = "mein-konto.php?neu=1";
  }catch(err){
    document.getElementById("msg").innerHTML = `<div class="auth-err">${err.message.replace(/</g,"&lt;")}</div>`;
    document.getElementById("msg").scrollIntoView({behavior:"smooth", block:"center"});
    btn.disabled = false; btn.classList.remove("loading");
  }
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

// ---------- Registrierungs-Wizard (3 Schritte) + Live-Vorschau ----------
(function(){
  const steps = [...document.querySelectorAll(".wiz-step")];
  const inds  = [...document.querySelectorAll(".wiz-step-ind")];
  const back = document.getElementById("wiz-back"), next = document.getElementById("wiz-next");
  const submit = document.getElementById("wiz-submit"), prev = document.getElementById("wiz-preview");
  let akt = 1;
  const zeige = n => {
    akt = n;
    steps.forEach(s => s.hidden = +s.dataset.step !== n);
    inds.forEach(i => { const s = +i.dataset.s; i.classList.toggle("active", s === n); i.classList.toggle("done", s < n); });
    back.hidden = n === 1;
    next.hidden = n === steps.length;
    submit.hidden = prev.hidden = n !== steps.length;
    document.querySelector(".reg-card").scrollIntoView({behavior:"smooth", block:"start"});
  };
  const gueltig = n => {
    for(const f of steps[n-1].querySelectorAll("input,select,textarea")){
      if(!f.checkValidity()){ f.reportValidity(); return false; }
    }
    if(n === 1 && ![...document.querySelectorAll('input[name="alter"]:checked')].length){ alert("Bitte mindestens eine Altersgruppe auswählen."); return false; }
    return true;
  };
  next.addEventListener("click", () => { if(gueltig(akt)) zeige(akt + 1); });
  back.addEventListener("click", () => zeige(akt - 1));
  inds.forEach(i => i.addEventListener("click", () => { const z = +i.dataset.s; if(z <= akt || gueltig(akt)) zeige(z); }));
  zeige(1);

  // Punkt 9: Live-Vorschau „So sehen Eltern dich"
  prev.addEventListener("click", () => {
    const g = id => document.getElementById(id).value.trim();
    const name = g("in-name") || "(dein Name)";
    const ort = document.getElementById("in-ort").value || "(Ort)";
    const bl = regionBundesland(document.getElementById("in-bundesland"));
    const plaetze = +document.getElementById("in-plaetze").value;
    const alter = [...document.querySelectorAll('input[name="alter"]:checked')].map(c => c.value);
    const extras = [...document.querySelectorAll('input[name="extras"]:checked')].map(c => c.value);
    const badges = badgePlaetze(plaetze) + (document.getElementById("in-erlaubnis").checked ? '<span class="badge b-check">✓ Pflegeerlaubnis §43</span>' : "");
    const fotoSrc = fotoBlob ? URL.createObjectURL(fotoBlob) : "";
    const ov = document.createElement("div");
    ov.className = "lightbox open";
    ov.innerHTML = `<button class="lb-close" aria-label="Schließen">✕</button>
      <div class="vorschau-card">
        <div class="vs-head">
          ${fotoSrc ? `<div class="p-photo"><img src="${fotoSrc}" alt=""></div>` : `<div class="p-photo" style="background:${avColor(name)}"><span class="ini">${esc((name.trim()[0]||"?"))}</span></div>`}
          <div><h2 class="vs-name">${esc(name)}</h2><div class="vs-ort">📍 ${esc(ort)}${bl ? " · " + esc(bl) : ""}</div></div>
        </div>
        <div class="badges" style="margin:.9rem 0">${badges}</div>
        <div class="p-meta"><span>🕐 <b>${esc(g("in-zeiten") || "—")}</b></span><span class="chips">${alter.map(a => `<span class="chip">${esc(a)}</span>`).join("")}${extras.map(x => `<span class="chip">✓ ${esc(x)}</span>`).join("")}</span></div>
        <p class="vs-text">${esc(g("in-text") || "(deine Vorstellung erscheint hier)")}</p>
        <p class="vs-note">— So sehen Eltern dein Profil —</p>
      </div>`;
    document.body.appendChild(ov);
    document.body.style.overflow = "hidden";
    const zu = () => { ov.remove(); document.body.style.overflow = ""; };
    ov.addEventListener("click", e => { if(e.target === ov || e.target.classList.contains("lb-close")) zu(); });
  });
})();
</script>
</body>
</html>

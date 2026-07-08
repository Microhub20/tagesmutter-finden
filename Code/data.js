// Tagesmutter finden – gemeinsame Helfer + Server-Anbindung (index / profil / Formulare)

// Orte nach Bundesland. Aktuell voll ausgebaut: Baden-Württemberg mit ALLEN Städten &
// Gemeinden des Zollernalbkreises (+ einige Nachbar-/Großstädte). Die übrigen Bundesländer
// sind mit ihren größten Städten als Start hinterlegt. Skalierbar auf ganz Deutschland:
// pro Bundesland einfach ergänzen; bei sehr vielen Orten später Autocomplete/PLZ-Suche.
const BUNDESLAENDER = {
  "Baden-Württemberg": ["Aalen", "Achern", "Albstadt", "Backnang", "Bad Friedrichshall", "Bad Krozingen", "Bad Mergentheim", "Bad Rappenau", "Bad Säckingen", "Bad Saulgau", "Bad Waldsee", "Baden-Baden", "Balingen", "Biberach an der Riß", "Bietigheim-Bissingen", "Blaustein", "Böblingen", "Brackenheim", "Breisach am Rhein", "Bretten", "Bruchsal", "Buchen (Odenwald)", "Bühl", "Burladingen", "Calw", "Crailsheim", "Ditzingen", "Donaueschingen", "Ebersbach an der Fils", "Ehingen (Donau)", "Eislingen/Fils", "Ellwangen (Jagst)", "Emmendingen", "Eppelheim", "Eppingen", "Esslingen am Neckar", "Ettlingen", "Fellbach", "Filderstadt", "Freiberg am Neckar", "Freiburg im Breisgau", "Freudenstadt", "Friedrichshafen", "Gaggenau", "Geislingen an der Steige", "Gerlingen", "Giengen an der Brenz", "Göppingen", "Haigerloch", "Hechingen", "Heidelberg", "Heidenheim an der Brenz", "Heilbronn", "Herrenberg", "Hockenheim", "Horb am Neckar", "Isny im Allgäu", "Karlsruhe", "Kehl", "Kirchheim unter Teck", "Konstanz", "Korntal-Münchingen", "Kornwestheim", "Künzelsau", "Lahr/Schwarzwald", "Langenau", "Laupheim", "Leimen", "Leinfelden-Echterdingen", "Leonberg", "Leutkirch im Allgäu", "Lörrach", "Ludwigsburg", "Mannheim", "Marbach am Neckar", "Meßstetten", "Metzingen", "Mosbach", "Mössingen", "Mühlacker", "Müllheim", "Nagold", "Neckarsulm", "Nürtingen", "Oberkirch", "Offenburg", "Öhringen", "Ostfildern", "Pforzheim", "Pfullingen", "Radolfzell am Bodensee", "Rastatt", "Ravensburg", "Remseck am Neckar", "Renningen", "Reutlingen", "Rheinfelden (Baden)", "Rheinstetten", "Rottenburg am Neckar", "Rottweil", "Sachsenheim", "Schopfheim", "Schorndorf", "Schramberg", "Schriesheim", "Schwäbisch Gmünd", "Schwäbisch Hall", "Schwetzingen", "Sigmaringen", "Sindelfingen", "Singen (Hohentwiel)", "Sinsheim", "Stockach", "Stutensee", "Stuttgart", "Tettnang", "Trossingen", "Tübingen", "Tuttlingen", "Überlingen", "Ulm", "Vaihingen an der Enz", "Villingen-Schwenningen", "Waghäusel", "Waiblingen", "Waldkirch", "Waldshut-Tiengen", "Walldorf", "Wangen im Allgäu", "Weil am Rhein", "Weil der Stadt", "Weingarten", "Weinheim", "Weinstadt", "Wendlingen am Neckar", "Wertheim", "Wiesloch", "Winnenden"],
  "Bayern": ["Augsburg","Ingolstadt","München","Nürnberg","Regensburg","Würzburg"],
  "Berlin": ["Berlin"],
  "Brandenburg": ["Brandenburg an der Havel","Cottbus","Frankfurt (Oder)","Potsdam"],
  "Bremen": ["Bremen","Bremerhaven"],
  "Hamburg": ["Hamburg"],
  "Hessen": ["Darmstadt","Frankfurt am Main","Kassel","Offenbach am Main","Wiesbaden"],
  "Mecklenburg-Vorpommern": ["Greifswald","Neubrandenburg","Rostock","Schwerin","Stralsund"],
  "Niedersachsen": ["Braunschweig","Göttingen","Hannover","Oldenburg","Osnabrück","Wolfsburg"],
  "Nordrhein-Westfalen": ["Aachen","Bielefeld","Bochum","Bonn","Dortmund","Duisburg","Düsseldorf","Essen","Köln","Münster"],
  "Rheinland-Pfalz": ["Kaiserslautern","Koblenz","Ludwigshafen am Rhein","Mainz","Trier"],
  "Saarland": ["Homburg","Neunkirchen","Saarbrücken"],
  "Sachsen": ["Chemnitz","Dresden","Leipzig","Zwickau"],
  "Sachsen-Anhalt": ["Dessau-Roßlau","Halle (Saale)","Magdeburg"],
  "Schleswig-Holstein": ["Flensburg","Kiel","Lübeck","Neumünster"],
  "Thüringen": ["Erfurt","Gera","Jena","Weimar"]
};
const BUNDESLAND_NAMEN = Object.keys(BUNDESLAENDER);

// Betreuungs-Extras (Checkboxen) – zusätzlich zu den Betreuungszeiten
const EXTRAS = ["Frühbetreuung", "Randzeiten", "Wochenende", "Ferienbetreuung", "Notfallbetreuung"];

const AVATAR_COLORS = ["#f2a25c","#6aa87e","#7f9fd1","#d17fa8","#a58bd1","#5cbdb9"];

const avColor = name => AVATAR_COLORS[[...String(name)].reduce((a,c) => a + c.charCodeAt(0), 0) % AVATAR_COLORS.length];
const esc = s => String(s).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));

function badgePlaetze(p){
  if(p >= 2) return `<span class="badge b-frei">🟢 ${p} Plätze frei</span>`;
  if(p === 1) return `<span class="badge b-frei">🟢 1 Platz frei</span>`;
  return `<span class="badge b-voll">Warteliste</span>`;
}

function profilUrl(id){ return "/profil/" + encodeURIComponent(id); }

// Relative Zeit ("vor 3 Tagen") + Frische-Badge (Vertrauenssignal)
function zeitRelativ(ts){
  if(!ts) return "";
  const d = new Date(String(ts).replace(" ", "T"));
  if(isNaN(d)) return "";
  const tage = Math.floor((Date.now() - d.getTime()) / 86400000);
  if(tage <= 0) return "heute";
  if(tage === 1) return "gestern";
  if(tage < 7) return "vor " + tage + " Tagen";
  if(tage < 21) return "vor " + Math.floor(tage/7) + " Woche(n)";
  if(tage < 365) return "vor " + Math.floor(tage/30) + " Monaten";
  return "vor " + Math.floor(tage/365) + " Jahr(en)";
}
function frischeBadge(e){
  const ts = e.updated_at || e.created_at;
  if(!ts) return "";
  const d = new Date(String(ts).replace(" ", "T"));
  if(isNaN(d)) return "";
  const tage = Math.floor((Date.now() - d.getTime()) / 86400000);
  return tage <= 14 ? '<span class="badge b-frisch" title="Profil in den letzten 2 Wochen aktualisiert">🕒 aktuell</span>' : "";
}

// Telefonnummer für WhatsApp normalisieren (dt. Format → internationale Ziffern) + Mobil-Erkennung
function waNummer(tel){
  let n = String(tel).replace(/[^\d+]/g, "");
  if(n.startsWith("+")) n = n.slice(1);
  else if(n.startsWith("00")) n = n.slice(2);
  else if(n.startsWith("0")) n = "49" + n.slice(1); // deutsche 0 → 49
  return n;
}
function istHandy(tel){
  const n = String(tel).replace(/[^\d]/g, "");
  return /^(0|0049|49)1[5-7]\d/.test(n); // dt. Mobilfunk: 015x/016x/017x
}

// ---------- Merkliste (Favoriten, lokal im Browser) ----------
const FAV_KEY = "tmf_favoriten";
function favGet(){ try{ return JSON.parse(localStorage.getItem(FAV_KEY) || "[]"); }catch(e){ return []; } }
function favHas(id){ return favGet().includes(id); }
function favToggle(id){
  const f = favGet(); const i = f.indexOf(id);
  if(i >= 0) f.splice(i, 1); else f.push(id);
  localStorage.setItem(FAV_KEY, JSON.stringify(f));
  return f.includes(id);
}

// Zwei abhängige Dropdowns füllen: Bundesland → Stadt. `blSel`/`ortSel` sind <select>-Elemente.
// optBl = "Alle Bundesländer" (für Filter) oder null (für Formular: erstes Bundesland vorwählen).
function initOrtsauswahl(blSel, ortSel, aktBl, aktOrt, filter){
  blSel.innerHTML = (filter ? '<option value="">Alle Bundesländer</option>' : '')
    + BUNDESLAND_NAMEN.map(b => `<option${b===aktBl?" selected":""}>${b}</option>`).join("");
  const fuelleOrte = () => {
    const bl = blSel.value;
    const orte = BUNDESLAENDER[bl] || [];
    ortSel.innerHTML = (filter ? '<option value="">Alle Orte</option>' : '')
      + orte.map(o => `<option${o===aktOrt?" selected":""}>${o}</option>`).join("");
    ortSel.disabled = !filter && !bl;
  };
  blSel.addEventListener("change", fuelleOrte);
  fuelleOrte();
}

// ======================= Regions-Konfiguration =======================
// Zum Start rollen wir NUR in Baden-Württemberg aus. Die Deutschland-weite
// Bundesland→Stadt-Auswahl (BUNDESLAENDER + initOrtsauswahl oben) ist NICHT
// gelöscht – nur ausgeblendet.
//   ▶ Ganz Deutschland wieder aktivieren: einfach REGION_NUR_BW = false setzen.
const REGION_NUR_BW = true;
const REGION_BL = "Baden-Württemberg";

// Städte der aktuellen Region direkt (ohne Bundesland-Auswahl).
function initBwOrte(ortSel, aktOrt, filter){
  const orte = BUNDESLAENDER[REGION_BL] || [];
  ortSel.innerHTML = (filter
      ? '<option value="">Alle Städte</option>'
      : '<option value="" disabled' + (aktOrt ? '' : ' selected') + '>Stadt / Gemeinde wählen …</option>')
    + orte.map(o => `<option${o === aktOrt ? " selected" : ""}>${o}</option>`).join("");
}

// Zentrale Auswahl für Filter & Formulare. Blendet je nach REGION_NUR_BW die
// Bundesland-Auswahl aus (→ BW-Städte direkt) oder zeigt die volle
// Deutschland-Kaskade. Das Bundesland-Feld bleibt im HTML (mit [data-bl-feld]
// markiert) und wird im BW-Modus nur versteckt. blSel darf null sein.
function initStadtauswahl(blSel, ortSel, aktBl, aktOrt, filter){
  const feld = blSel ? (blSel.closest("[data-bl-feld]") || blSel) : null;
  if(REGION_NUR_BW){
    if(feld) feld.hidden = true;
    // Formular-Bundesland-Feld versteckt mit BW belegen (damit Form-POST korrekt sendet)
    if(blSel && !filter) blSel.innerHTML = '<option value="' + REGION_BL + '" selected>' + REGION_BL + '</option>';
    initBwOrte(ortSel, aktOrt, filter);
  }else{
    if(feld) feld.hidden = false;
    initOrtsauswahl(blSel, ortSel, aktBl || REGION_BL, aktOrt, filter);
  }
}
// Bundesland-Wert fürs Speichern (BW-Modus: fix; Deutschland: aus dem Feld)
function regionBundesland(blSel){
  return REGION_NUR_BW ? REGION_BL : (blSel ? blSel.value : REGION_BL);
}
// URL-Slug einer Stadt (spiegelt PHP tmf_slug) + Link zur Stadt-Landingpage
function ortSlug(s){
  return String(s).toLowerCase()
    .replace(/ä/g,"ae").replace(/ö/g,"oe").replace(/ü/g,"ue").replace(/ß/g,"ss")
    .replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");
}
function stadtUrl(ort){ return "/tagesmutter/" + ortSlug(ort); }

// ---------- Server-Anbindung ----------
async function ladeEintraege(){
  const res = await fetch("api/list.php", {headers:{"Accept":"application/json"}});
  if(!res.ok) throw new Error("Laden fehlgeschlagen");
  return await res.json();
}
async function ladeProfil(id){
  const res = await fetch("api/list.php?id=" + encodeURIComponent(id), {headers:{"Accept":"application/json"}});
  if(res.status === 404) return null;
  if(!res.ok) throw new Error("Laden fehlgeschlagen");
  return await res.json();
}

// ---------- Mobile-Navigation (Hamburger) – aktiv auf jeder Seite mit #nav-toggle ----------
(function(){
  const t = document.getElementById("nav-toggle");
  const n = document.getElementById("hauptnav");
  if(!t || !n) return;
  const bd = document.createElement("div");
  bd.className = "nav-backdrop";
  document.body.appendChild(bd);
  const setz = open => {
    t.classList.toggle("open", open);
    n.classList.toggle("open", open);
    bd.classList.toggle("show", open);
    t.setAttribute("aria-expanded", open ? "true" : "false");
    t.setAttribute("aria-label", open ? "Menü schließen" : "Menü öffnen");
    document.body.style.overflow = open ? "hidden" : "";
  };
  t.addEventListener("click", () => setz(!n.classList.contains("open")));
  bd.addEventListener("click", () => setz(false));
  n.addEventListener("click", ev => { if(ev.target.closest("a")) setz(false); });
  addEventListener("keydown", ev => { if(ev.key === "Escape") setz(false); });
})();

// ---------- „Nach oben"-Button (jede Seite mit data.js) ----------
(function(){
  const b = document.createElement("button");
  b.className = "to-top"; b.type = "button"; b.setAttribute("aria-label", "Nach oben scrollen"); b.textContent = "↑";
  document.body.appendChild(b);
  addEventListener("scroll", () => b.classList.toggle("show", scrollY > 700), {passive:true});
  b.addEventListener("click", () => scrollTo({top:0, behavior:"smooth"}));
})();

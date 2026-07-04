// Tagesmutter finden – gemeinsame Helfer + Server-Anbindung (index / profil / Formulare)

// Orte nach Bundesland. Aktuell voll ausgebaut: Baden-Württemberg mit ALLEN Städten &
// Gemeinden des Zollernalbkreises (+ einige Nachbar-/Großstädte). Die übrigen Bundesländer
// sind mit ihren größten Städten als Start hinterlegt. Skalierbar auf ganz Deutschland:
// pro Bundesland einfach ergänzen; bei sehr vielen Orten später Autocomplete/PLZ-Suche.
const BUNDESLAENDER = {
  "Baden-Württemberg": ["Albstadt","Balingen","Bisingen","Bitz","Burladingen","Dautmergen","Dormettingen","Dotternhausen","Freiburg im Breisgau","Geislingen","Grosselfingen","Haigerloch","Hausen am Tann","Hechingen","Heidelberg","Jungingen","Karlsruhe","Mannheim","Meßstetten","Nusplingen","Obernheim","Rangendingen","Ratshausen","Reutlingen","Rosenfeld","Rottweil","Schömberg","Straßberg","Stuttgart","Tübingen","Ulm","Weilen unter den Rinnen","Winterlingen","Zimmern unter der Burg"],
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

const AVATAR_COLORS = ["#f2a25c","#6aa87e","#7f9fd1","#d17fa8","#a58bd1","#5cbdb9"];

const avColor = name => AVATAR_COLORS[[...String(name)].reduce((a,c) => a + c.charCodeAt(0), 0) % AVATAR_COLORS.length];
const esc = s => String(s).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));

function badgePlaetze(p){
  if(p >= 2) return `<span class="badge b-frei">🟢 ${p} Plätze frei</span>`;
  if(p === 1) return `<span class="badge b-frei">🟢 1 Platz frei</span>`;
  return `<span class="badge b-voll">Warteliste</span>`;
}

function profilUrl(id){ return "profil.html?id=" + encodeURIComponent(id); }

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

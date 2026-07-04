// Tagesmutter finden – gemeinsame Helfer + Server-Anbindung (index.html & profil.html)

const STADTTEILE = ["Mitte","Frommern","Weilstetten","Engstlatt","Ostdorf","Heselwangen","Endingen","Erzingen","Roßwangen","Dürrwangen","Zillhausen","Streichen","Stockenhausen"];

const AVATAR_COLORS = ["#f2a25c","#6aa87e","#7f9fd1","#d17fa8","#a58bd1","#5cbdb9"];

// Stabile Avatar-Farbe aus dem Namen (Liste und Profilseite zeigen dieselbe Farbe)
const avColor = name => AVATAR_COLORS[[...String(name)].reduce((a,c) => a + c.charCodeAt(0), 0) % AVATAR_COLORS.length];
const esc = s => String(s).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));

function badgePlaetze(p){
  if(p >= 2) return `<span class="badge b-frei">🟢 ${p} Plätze frei</span>`;
  if(p === 1) return `<span class="badge b-frei">🟢 1 Platz frei</span>`;
  return `<span class="badge b-voll">Warteliste</span>`;
}

function profilUrl(id){ return "profil.html?id=" + encodeURIComponent(id); }

// ---------- Server-Anbindung ----------
// Freigegebene Einträge laden (alle oder ein einzelnes Profil per id)
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

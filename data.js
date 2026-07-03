// Tagesmutter finden – gemeinsame Daten & Helfer (genutzt von index.html und profil.html)

const STADTTEILE = ["Mitte","Frommern","Weilstetten","Engstlatt","Ostdorf","Heselwangen","Endingen","Erzingen","Roßwangen","Dürrwangen","Zillhausen","Streichen","Stockenhausen"];

const DEMO = [
  {id:"sabine-k", name:"Sabine K.", ort:"Frommern", plaetze:2, zeiten:"Mo–Fr 7:00–15:00", alter:["0–1 Jahr","1–3 Jahre"], erlaubnis:true,
   text:"Erfahrene Tagesmutter seit 2015. Großer Garten, tägliche Ausflüge in die Natur und selbstgekochte Mahlzeiten.",
   persoenlich:"Hallo! Ich bin Sabine, 46 Jahre alt, Mama von zwei (inzwischen großen) Kindern – und seit 2015 Tagesmutter mit Herz und Pflegeerlaubnis.\n\nBei mir wird gelebt, gelacht und vor allem viel draußen gespielt: Unser großer Garten mit Sandkasten und Hochbeet ist im Sommer unser zweites Wohnzimmer, und einmal am Tag geht es raus – bei fast jedem Wetter, in den Wald oder auf den Spielplatz um die Ecke.\n\nMittags koche ich frisch und kindgerecht, gern mit Gemüse aus dem eigenen Beet. Mir ist wichtig, dass sich jedes Kind bei mir geborgen fühlt und die Welt in seinem eigenen Tempo entdecken darf. Feste Rituale – gemeinsames Frühstück, Mittagsschlaf, Vorlesezeit – geben den Kleinen Halt.\n\nIch freue mich darauf, dich und dein Kind kennenzulernen!",
   email:"sabine.k@beispiel.de", tel:"07433 000001"},
  {id:"melanie-r", name:"Melanie R.", ort:"Weilstetten", plaetze:1, zeiten:"Mo–Do 7:30–16:30", alter:["1–3 Jahre"], erlaubnis:true,
   text:"Staatlich anerkannte Erzieherin. Ich betreue maximal 4 Kinder in familiärer Atmosphäre – Schwerpunkt Musik & Bewegung.",
   persoenlich:"Schön, dass du da bist! Ich bin Melanie, staatlich anerkannte Erzieherin. Nach zehn Jahren Kita-Arbeit habe ich mich bewusst für die Kindertagespflege entschieden: Ich möchte Kinder wieder wirklich begleiten können – mit Zeit, Ruhe und echter Nähe.\n\nBei mir werden maximal vier Kinder betreut. Mein Schwerpunkt liegt auf Musik und Bewegung: Wir singen jeden Morgen im Begrüßungskreis, tanzen durchs Wohnzimmer und entdecken erste Instrumente.\n\nAls ausgebildete Fachkraft dokumentiere ich die Entwicklung deines Kindes und biete regelmäßige Elterngespräche an. Die Eingewöhnung gestalte ich sanft nach dem Berliner Modell – so lange, wie dein Kind es braucht.",
   email:"melanie.r@beispiel.de", tel:"07433 000002"},
  {id:"fatma-oe", name:"Fatma Ö.", ort:"Mitte", plaetze:0, zeiten:"Mo–Fr 7:00–17:00", alter:["0–1 Jahr","1–3 Jahre"], erlaubnis:true,
   text:"Zweisprachige Betreuung (Deutsch/Türkisch), zentral gelegen, Spielplatz direkt vor der Tür. Aktuell Warteliste.",
   persoenlich:"Merhaba und hallo! Ich bin Fatma, zweifache Mama und seit acht Jahren Tagesmutter mitten in Balingen. Bei mir wachsen die Kinder ganz selbstverständlich mit zwei Sprachen auf – Deutsch und Türkisch – spielerisch und ohne Druck.\n\nWir sind fast jeden Tag auf dem Spielplatz direkt vor der Haustür, backen zusammen, basteln viel und feiern die Feste beider Kulturen. Mittags gibt es frisch gekochtes Essen – meine Linsensuppe ist bei allen Kindern legendär.\n\nAktuell sind alle Plätze belegt, aber die Warteliste lohnt sich: Zum neuen Kita-Jahr wechseln meist ein bis zwei Kinder in den Kindergarten, dann werden Plätze frei.",
   email:"fatma.oe@beispiel.de", tel:"07433 000003"},
  {id:"claudia-b", name:"Claudia B.", ort:"Engstlatt", plaetze:3, zeiten:"Flexibel, auch Randzeiten & Sa", alter:["1–3 Jahre","3+ Jahre"], erlaubnis:true,
   text:"Auch Randzeiten- und Schulkindbetreuung möglich – ideal bei Schichtarbeit. Qualifizierte Kindertagespflegeperson.",
   persoenlich:"Ich bin Claudia, gelernte Kinderpflegerin und seit 2018 qualifizierte Kindertagespflegeperson. Mein Angebot richtet sich besonders an Familien, die flexible Betreuung brauchen: Frühdienst ab 5:30 Uhr, Randzeiten am Abend und nach Absprache auch Samstage – ich weiß aus eigener Erfahrung, wie herausfordernd Schichtarbeit mit kleinen Kindern ist.\n\nNeben den Kleinen betreue ich auch Schulkinder vor und nach dem Unterricht, inklusive Hausaufgabenbegleitung und Begleitung zur Grundschule Engstlatt.\n\nBei mir gibt es klare Abläufe, viel frische Luft und ein offenes Ohr für die Eltern. Ruf einfach an – gemeinsam finden wir ein Betreuungsmodell, das zu eurem Familienalltag passt.",
   email:"claudia.b@beispiel.de", tel:"07433 000004"},
  {id:"jennifer-m", name:"Jennifer M.", ort:"Ostdorf", plaetze:1, zeiten:"Mo–Fr 8:00–14:00", alter:["1–3 Jahre"], erlaubnis:true,
   text:"Naturnahe Betreuung mit viel Zeit im Freien (Waldpädagogik). Ein Platz frei ab September 2026.",
   persoenlich:"Hallo, ich bin Jenny! Bevor ich Tagesmutter wurde, habe ich als Erzieherin in einem Waldkindergarten gearbeitet – und diese Liebe zur Natur prägt meine Betreuung bis heute.\n\nBei mir sind die Kinder jeden Vormittag draußen: Wir sammeln Kastanien, beobachten Käfer, matschen im Regen und bauen Tipis am Waldrand von Ostdorf. Es gibt kein schlechtes Wetter, nur falsche Kleidung! Drinnen wird anschließend gewerkelt, vorgelesen und geruht.\n\nIch betreue bewusst nur eine kleine Gruppe, damit ich jedem Kind gerecht werde. Ab September 2026 wird ein Platz frei – gern kannst du dein Kind schon jetzt unverbindlich vormerken lassen.",
   email:"jennifer.m@beispiel.de", tel:"07433 000005"},
];

const AVATAR_COLORS = ["#f2a25c","#6aa87e","#7f9fd1","#d17fa8","#a58bd1","#5cbdb9"];
const LS_KEY = "tmf_entries_v1";

// Stabile Avatar-Farbe aus dem Namen (Liste und Profilseite zeigen dieselbe Farbe)
const avColor = name => AVATAR_COLORS[[...String(name)].reduce((a,c) => a + c.charCodeAt(0), 0) % AVATAR_COLORS.length];
const slug = s => String(s).toLowerCase()
  .replace(/ä/g,"ae").replace(/ö/g,"oe").replace(/ü/g,"ue").replace(/ß/g,"ss")
  .replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"") || "eintrag";
const esc = s => String(s).replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));

function loadOwn(){
  try{
    const list = JSON.parse(localStorage.getItem(LS_KEY)) || [];
    list.forEach((e,i) => { if(!e.id) e.id = "eigen-" + i; }); // Alt-Einträge ohne ID
    return list;
  }catch(e){ return []; }
}
function saveOwn(list){ localStorage.setItem(LS_KEY, JSON.stringify(list)); }

function alleEintraege(){
  const own = loadOwn().map(e => ({...e, neu:true}));
  return [...own, ...DEMO];
}

function badgePlaetze(p){
  if(p >= 2) return `<span class="badge b-frei">🟢 ${p} Plätze frei</span>`;
  if(p === 1) return `<span class="badge b-frei">🟢 1 Platz frei</span>`;
  return `<span class="badge b-voll">Warteliste</span>`;
}

function profilUrl(id){ return "profil.html?id=" + encodeURIComponent(id); }

<?php
/**
 * Fügt Beispiel-Tagesmütter als Startinhalt ein (Status "approved").
 * Aufruf nur mit Admin-Schlüssel:  api/seed.php?key=<ADMIN_PASS>
 * Idempotent: vorhandene Demo-Einträge (feste IDs) werden ersetzt.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

$cfg = tmf_config();
if (!hash_equals((string)($cfg['ADMIN_PASS'] ?? ''), (string)($_GET['key'] ?? ''))) {
    tmf_json(['error' => 'forbidden'], 403);
}

$demo = [
  ['sabine-k','Sabine K.','Frommern',2,'Mo–Fr 7:00–15:00',['0–1 Jahr','1–3 Jahre'],1,
   "Hallo! Ich bin Sabine, 46 Jahre alt, Mama von zwei (inzwischen großen) Kindern – und seit 2015 Tagesmutter mit Herz und Pflegeerlaubnis.\n\nBei mir wird gelebt, gelacht und vor allem viel draußen gespielt: Unser großer Garten mit Sandkasten und Hochbeet ist im Sommer unser zweites Wohnzimmer, und einmal am Tag geht es raus – bei fast jedem Wetter, in den Wald oder auf den Spielplatz um die Ecke.\n\nMittags koche ich frisch und kindgerecht. Mir ist wichtig, dass sich jedes Kind bei mir geborgen fühlt und die Welt in seinem eigenen Tempo entdecken darf.",
   'sabine.k@beispiel.de','07433 000001'],
  ['melanie-r','Melanie R.','Weilstetten',1,'Mo–Do 7:30–16:30',['1–3 Jahre'],1,
   "Schön, dass du da bist! Ich bin Melanie, staatlich anerkannte Erzieherin. Nach zehn Jahren Kita-Arbeit habe ich mich bewusst für die Kindertagespflege entschieden.\n\nBei mir werden maximal vier Kinder betreut. Mein Schwerpunkt liegt auf Musik und Bewegung: Wir singen jeden Morgen im Begrüßungskreis, tanzen durchs Wohnzimmer und entdecken erste Instrumente.",
   'melanie.r@beispiel.de','07433 000002'],
  ['fatma-oe','Fatma Ö.','Mitte',0,'Mo–Fr 7:00–17:00',['0–1 Jahr','1–3 Jahre'],1,
   "Merhaba und hallo! Ich bin Fatma, zweifache Mama und seit acht Jahren Tagesmutter mitten in Balingen. Bei mir wachsen die Kinder ganz selbstverständlich mit zwei Sprachen auf – Deutsch und Türkisch.\n\nWir sind fast jeden Tag auf dem Spielplatz direkt vor der Haustür, backen zusammen und feiern die Feste beider Kulturen. Aktuell sind alle Plätze belegt, aber die Warteliste lohnt sich.",
   'fatma.oe@beispiel.de','07433 000003'],
  ['claudia-b','Claudia B.','Engstlatt',3,'Flexibel, auch Randzeiten & Sa',['1–3 Jahre','3+ Jahre'],1,
   "Ich bin Claudia, gelernte Kinderpflegerin und seit 2018 qualifizierte Kindertagespflegeperson. Mein Angebot richtet sich besonders an Familien, die flexible Betreuung brauchen: Frühdienst, Randzeiten und nach Absprache auch Samstage.\n\nNeben den Kleinen betreue ich auch Schulkinder vor und nach dem Unterricht. Ruf einfach an – gemeinsam finden wir ein Modell, das zu eurem Alltag passt.",
   'claudia.b@beispiel.de','07433 000004'],
  ['jennifer-m','Jennifer M.','Ostdorf',1,'Mo–Fr 8:00–14:00',['1–3 Jahre'],1,
   "Hallo, ich bin Jenny! Bevor ich Tagesmutter wurde, habe ich als Erzieherin in einem Waldkindergarten gearbeitet – und diese Liebe zur Natur prägt meine Betreuung bis heute.\n\nBei mir sind die Kinder jeden Vormittag draußen: Wir sammeln Kastanien, beobachten Käfer und bauen Tipis am Waldrand. Ab September 2026 wird ein Platz frei.",
   'jennifer.m@beispiel.de','07433 000005'],
];

$pdo = tmf_db();
$del = $pdo->prepare("DELETE FROM tagesmuetter WHERE id = ?");
$ins = $pdo->prepare(
    "INSERT INTO tagesmuetter
     (id,name,ort,plaetze,zeiten,altersgruppen,persoenlich,email,tel,erlaubnis,foto,status)
     VALUES (?,?,?,?,?,?,?,?,?,?,NULL,'approved')"
);
foreach ($demo as $d) {
    $del->execute([$d[0]]);
    $ins->execute([$d[0],$d[1],$d[2],$d[3],$d[4], json_encode($d[5], JSON_UNESCAPED_UNICODE), $d[7],$d[8],$d[9],$d[6]]);
}
tmf_json(['ok' => true, 'eingefuegt' => count($demo)]);

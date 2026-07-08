<?php
/**
 * Tagesmutter-Profil – server-seitig gerendert (SEO).
 * URL: /profil/<id>   (per .htaccess → profil.php?id=<id>; id ist bereits ein Slug)
 * Titel, Meta-Description, OG-Tags, schema.org und der Kern-Inhalt (Name, Ort,
 * Vorstellung) stehen im HTML-Quelltext – nicht per JS – damit Google + KI sie sehen.
 * Die Interaktivität (Anfrage, Galerie, Merken, ähnliche) ergänzt data.js über das
 * eingebettete window.__PROFIL (kein zusätzlicher fetch, kein Flackern).
 */
declare(strict_types=1);
require __DIR__ . '/api/db.php';

$e  = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$id = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['id'] ?? '')));

$entry = null;
if ($id !== '') {
    try {
        $pdo = tmf_db();
        $st = $pdo->prepare("SELECT * FROM tagesmuetter WHERE id = ? AND status = 'approved'");
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) $entry = tmf_row_to_entry($row);
    } catch (Throwable $ex) { $entry = null; }
}
if ($entry === null) http_response_code(404);

$base  = 'https://mein-tageskind.de';
$canon = $entry ? $base . '/profil/' . rawurlencode($entry['id']) : $base . '/';

// ---- SEO-Meta aus echten Profildaten ----
if ($entry) {
    $titel  = $entry['name'] . ' – Tagesmutter in ' . $entry['ort'];
    $rohtxt = trim((string)$entry['persoenlich']) !== ''
        ? $entry['persoenlich']
        : ('Kindertagespflege bei ' . $entry['name'] . ' in ' . $entry['ort'] . ': Betreuungszeiten, freie Plätze und direkter Kontakt.');
    $desc   = mb_substr(trim(preg_replace('/\s+/', ' ', (string)$rohtxt)), 0, 155);
    $robots = 'index, follow';
    $ogimg  = $entry['foto'] ? $base . $entry['foto'] : $base . '/img/hero.jpg';
} else {
    $titel  = 'Profil nicht gefunden';
    $desc   = 'Dieses Profil existiert nicht (mehr) oder der Link ist unvollständig.';
    $robots = 'noindex, follow';
    $ogimg  = $base . '/img/hero.jpg';
}

// ---- Server-seitige Render-Helfer (Spiegel der JS-Logik in data.js) ----
function p_badge_plaetze(int $p): string {
    if ($p >= 2) return '<span class="badge b-frei">🟢 ' . $p . ' Plätze frei</span>';
    if ($p === 1) return '<span class="badge b-frei">🟢 1 Platz frei</span>';
    return '<span class="badge b-voll">Warteliste</span>';
}
function p_frische(array $x): string {
    $ts = $x['updated_at'] ?: $x['created_at'];
    if (!$ts) return '';
    $t = strtotime((string)$ts);
    if ($t === false) return '';
    $tage = (int)floor((time() - $t) / 86400);
    return $tage <= 14 ? '<span class="badge b-frisch" title="Profil in den letzten 2 Wochen aktualisiert">🕒 aktuell</span>' : '';
}

// ---- Kern-Inhalt server-seitig vorbereiten ----
$badgesHtml = '';
$metaHtml   = '';
if ($entry) {
    $badgesHtml = p_badge_plaetze((int)$entry['plaetze'])
        . ($entry['erlaubnis'] ? '<span class="badge b-check" title="Pflegeerlaubnis vom Jugendamt nach § 43 SGB VIII">✓ Pflegeerlaubnis §43</span>' : '')
        . p_frische($entry);

    $metaHtml = '<span>🕐 <b>' . $e($entry['zeiten']) . '</b></span>';
    $chips = '';
    foreach ((array)$entry['alter'] as $a) $chips .= '<span class="chip">' . $e($a) . '</span>';
    $metaHtml .= '<span class="chips">' . $chips . '</span>';
    if (!empty($entry['extras'])) {
        $ex = '';
        foreach ((array)$entry['extras'] as $x) $ex .= '<span class="chip">✓ ' . $e($x) . '</span>';
        $metaHtml .= '<span class="chips">' . $ex . '</span>';
    }
}

// ---- strukturierte Daten (schema.org): ChildCare + Breadcrumb ----
$schemas = [];
if ($entry) {
    $childcare = [
        '@context'    => 'https://schema.org',
        '@type'       => 'ChildCare',
        'name'        => $entry['name'],
        'url'         => $canon,
        'description' => $desc,
        'areaServed'  => $entry['ort'],
        'address'     => [
            '@type'           => 'PostalAddress',
            'addressLocality' => $entry['ort'],
            'addressRegion'   => $entry['bundesland'] ?: TMF_REGION,
            'addressCountry'  => 'DE',
        ],
        'email'       => $entry['email'],
    ];
    if (!empty($entry['tel']))  $childcare['telephone'] = $entry['tel'];
    if (!empty($entry['foto'])) $childcare['image'] = $base . $entry['foto'];
    if (!empty($entry['created_at'])) $childcare['datePublished'] = substr((string)$entry['created_at'], 0, 10);
    $mod = $entry['updated_at'] ?: $entry['created_at'];
    if (!empty($mod)) $childcare['dateModified'] = substr((string)$mod, 0, 10);
    $schemas[] = $childcare;
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite',   'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tagesmütter',  'item' => $base . '/#liste'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Tagesmütter in ' . $entry['ort'], 'item' => $base . '/tagesmutter/' . tmf_slug($entry['ort'])],
            ['@type' => 'ListItem', 'position' => 4, 'name' => $entry['name'],  'item' => $canon],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="<?= $robots ?>">
<meta name="description" content="<?= $e($desc) ?>">
<meta name="author" content="Gaseit GmbH">
<link rel="canonical" href="<?= $e($canon) ?>">
<meta property="og:type" content="profile">
<meta property="og:site_name" content="mein Tageskind">
<meta property="og:title" content="<?= $e($titel) ?>">
<meta property="og:description" content="<?= $e($desc) ?>">
<meta property="og:url" content="<?= $e($canon) ?>">
<meta property="og:image" content="<?= $e($ogimg) ?>">
<meta name="twitter:card" content="summary_large_image">
<title><?= $e($titel) ?> | mein Tageskind</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="/styles.css?v=2">
<?php foreach ($schemas as $s): ?>
<script type="application/ld+json"><?= json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>
<style>
  .p-details{margin:1.6rem 0}
  .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.7rem;margin-top:.7rem}
  .detail-item{display:flex;gap:.7rem;align-items:center;background:var(--cream);border:1px solid var(--line);border-radius:14px;padding:.65rem .85rem}
  .di-ic{font-size:1.4rem;line-height:1;flex-shrink:0}
  .di-label{display:block;font-size:.68rem;color:var(--muted);font-weight:800;text-transform:uppercase;letter-spacing:.04em}
  .di-val{display:block;font-weight:700;color:var(--ink);font-size:.92rem;line-height:1.25}
  .p-anfrage{margin-top:2rem;padding-top:1.6rem;border-top:1px solid var(--line)}
  .p-share{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1rem}
  .p-melden{margin-top:1.6rem;font-size:.82rem;text-align:center}
  .p-melden a{color:var(--muted);text-decoration:none;border-bottom:1px dotted var(--muted)}
  .p-melden a:hover{color:var(--coral);border-color:var(--coral)}
  .p-aehnliche{max-width:820px;margin:0 auto;padding:0 1.4rem 3rem}
  .aehnliche-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;margin-top:.8rem}
  .ae-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:1rem;text-decoration:none;color:var(--ink);display:block;transition:transform .15s;box-shadow:var(--shadow-sm)}
  .ae-card:hover{transform:translateY(-2px)}
  .ae-card h4{font-weight:800;font-size:1rem;margin-bottom:.2rem}
  .ae-card .ae-ort{color:var(--muted);font-size:.82rem;font-weight:700}
</style>
</head>
<body>
<a href="#profil" class="skip-link">Zum Inhalt springen</a>

<header id="header">
  <div class="header-inner">
    <a class="logo" href="/">
      <img src="/img/logo-mein-tageskind.png" alt="mein Tageskind" class="logo-img">
    </a>
    <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Menü öffnen" aria-expanded="false" aria-controls="hauptnav">
      <span></span><span></span><span></span>
    </button>
    <nav id="hauptnav">
      <a href="/#liste">Tagesmütter</a>
      <a href="/#so-gehts">So funktioniert’s</a>
      
      <a href="/#eintragen" class="cta">Als Tagesmutter eintragen</a>
    </nav>
  </div>
</header>

<main class="profile-main">
  <a class="back-link" href="/#liste">← Alle Tagesmütter</a>

  <!-- Profil – Kern server-seitig gerendert, Interaktivität ergänzt data.js -->
  <article class="profile-card" id="profil"<?= $entry ? '' : ' hidden' ?>>
    <div class="p-head">
      <div class="p-photo" id="p-photo"></div>
      <div>
        <h1 id="p-name"><?= $entry ? $e($entry['name']) : '' ?></h1>
        <div class="ort" id="p-ort"><?= $entry ? '📍 ' . $e($entry['ort']) . ($entry['bundesland'] ? ' · ' . $e($entry['bundesland']) : '') : '' ?></div>
      </div>
    </div>
    <div class="badges" id="p-badges"><?= $badgesHtml ?></div>
    <div class="p-meta" id="p-meta"><?= $metaHtml ?></div>
    <div class="p-details" id="p-details" hidden></div>
    <div class="p-galerie" id="p-galerie" hidden>
      <div class="haupt"><img id="p-gal-haupt" src="" alt="Bild der Tagesmutter"></div>
      <div class="thumbs" id="p-gal-thumbs"></div>
    </div>
    <h2 class="p-label">Persönliche Vorstellung</h2>
    <p class="p-text" id="p-text"><?= $entry ? nl2br($e($entry['persoenlich'] ?: 'Für dieses Profil wurde noch kein persönlicher Text hinterlegt.')) : '' ?></p>
    <div id="p-konzept" hidden></div>
    <div class="p-contact" id="p-contact"></div>
    <div class="p-share" id="p-share"></div>
    <details class="p-checkliste">
      <summary>💡 Fragen fürs Kennenlerngespräch</summary>
      <div class="pc-body">
        <p>Ein persönliches Kennenlernen hilft dir, die richtige Betreuung zu finden. Diese Fragen kannst du stellen:</p>
        <ul>
          <li>Wie sieht ein typischer Tagesablauf aus?</li>
          <li>Wie viele Kinder werden gleichzeitig betreut – und in welchem Alter?</li>
          <li>Wie laufen Eingewöhnung und Abschied ab?</li>
          <li>Was gibt es zu essen? Wie wird auf Allergien &amp; Ernährung eingegangen?</li>
          <li>Wie wird bei Krankheit oder Urlaub der Tagesmutter vertreten?</li>
          <li>Wie läuft die Kostenübernahme über das Jugendamt?</li>
          <li>Gibt es feste Rituale, Ausflüge oder täglich Zeit im Freien?</li>
        </ul>
      </div>
    </details>
<?php if ($entry): ?>
    <p class="p-melden"><a href="mailto:info@gaseit.de?subject=<?= $e(rawurlencode('Profil melden: ' . $entry['name'] . ' (' . $entry['id'] . ')')) ?>&amp;body=<?= $e(rawurlencode("Ich möchte dieses Profil melden:\n" . $canon . "\n\nGrund (bitte kurz beschreiben):\n")) ?>">⚠️ Stimmt etwas nicht mit diesem Profil? Melden</a></p>
<?php endif; ?>
    <div class="p-vormerkung" id="p-vormerkung" hidden>
      <h2 class="p-label">🔔 Bei freiem Platz benachrichtigen</h2>
      <p style="color:var(--muted);font-size:.9rem;margin:.2rem 0 .9rem">Aktuell keine freien Plätze. Trag dich ein – du wirst benachrichtigt, sobald wieder etwas frei wird.</p>
      <div id="vm-msg"></div>
      <form id="vm-form" novalidate>
        <div class="row">
          <div class="field"><label for="vm-name">Dein Name *</label><input type="text" id="vm-name" required maxlength="80"></div>
          <div class="field"><label for="vm-email">Deine E-Mail *</label><input type="email" id="vm-email" required maxlength="100" autocomplete="email"></div>
        </div>
        <input type="text" id="vm-hp" name="firma_website" style="display:none" tabindex="-1" autocomplete="off">
        <div class="submit-row" style="text-align:left"><button type="submit" class="btn btn-ghost">Vormerken</button></div>
      </form>
    </div>
    <div class="p-anfrage" id="p-anfrage" hidden>
      <h2 class="p-label">Direkt anfragen</h2>
      <p style="color:var(--muted);font-size:.9rem;margin:.2rem 0 .9rem">Schreib eine unverbindliche Nachricht – sie geht direkt an die Tagesmutter.</p>
      <div id="anfrage-msg"></div>
      <form id="anfrage-form" novalidate>
        <div class="row">
          <div class="field"><label for="a-name">Dein Name *</label><input type="text" id="a-name" required maxlength="80"></div>
          <div class="field"><label for="a-email">Deine E-Mail *</label><input type="email" id="a-email" required maxlength="100" autocomplete="email"></div>
        </div>
        <div class="field"><label for="a-tel">Telefon <span class="opt">(optional)</span></label><input type="tel" id="a-tel" maxlength="30"></div>
        <div class="field">
          <label for="a-text">Nachricht *</label>
          <div class="bausteine" id="a-bausteine">
            <button type="button" data-b="Ich suche einen Betreuungsplatz für mein Kind.">👶 Platz gesucht</button>
            <button type="button" data-b="Gewünschter Start: ab sofort.">🗓️ ab sofort</button>
            <button type="button" data-b="Betreuungsumfang: Vollzeit.">🕐 Vollzeit</button>
            <button type="button" data-b="Betreuungsumfang: Teilzeit.">🕐 Teilzeit</button>
            <button type="button" data-b="Können wir uns unverbindlich kennenlernen?">🤝 Kennenlernen</button>
          </div>
          <textarea id="a-text" required rows="4" maxlength="1500" placeholder="Hallo, ich suche einen Betreuungsplatz für mein Kind (… Jahre) ab …"></textarea>
        </div>
        <input type="text" id="a-hp" name="firma_website" style="display:none" tabindex="-1" autocomplete="off">
        <div class="submit-row" style="text-align:left"><button type="submit" class="btn btn-coral">Anfrage senden</button></div>
      </form>
    </div>
  </article>

  <section class="p-aehnliche" id="p-aehnliche" hidden></section>

  <!-- Fehlerzustand: Profil nicht gefunden -->
  <div class="profile-card p-error" id="fehler"<?= $entry ? ' hidden' : '' ?>>
    <div class="big">🧸</div>
    <h1>Profil nicht gefunden</h1>
    <p>Dieses Profil existiert nicht (mehr) oder der Link ist unvollständig.</p>
    <a class="btn btn-coral" href="/#liste">Zur Übersicht aller Tagesmütter</a>
  </div>
</main>

<footer>
  <div class="footer-grid">
    <div>
      <a class="logo" href="/">
        <img src="/img/logo-mein-tageskind.png" alt="mein Tageskind" class="logo-img" style="height:50px">
      </a>
      <p class="brand-txt">Das Verzeichnis für Kindertagespflege in deiner Region. Eltern finden Betreuung, Tagesmütter werden gefunden – einfach, direkt und kostenlos.</p>
    </div>
    <div>
      <h5>Für Eltern</h5>
      <ul>
        <li><a href="/#liste">Tagesmütter finden</a></li>
        <li><a href="/#so-gehts">So funktioniert’s</a></li>
        <li><a href="/#faq">Häufige Fragen</a></li>
      </ul>
    </div>
    <div>
      <h5>Für Tagesmütter</h5>
      <ul>
        <li><a href="/#eintragen">Kostenlos eintragen</a></li>
        <li><a href="/impressum.html">Impressum</a></li>
        <li><a href="/datenschutz.html">Datenschutz</a></li>
      </ul>
    </div>
  </div>
  <div class="powered">
    <span class="pw-label">Powered by</span>
    <a class="pw-gaseit" href="https://gaseit.de" target="_blank" rel="noopener" aria-label="Gaseit GmbH">
      <img src="/img/gaseit-logo.png?v=2" alt="Gaseit GmbH" class="pw-gaseit-img">
    </a>
  </div>
  <div class="footer-bottom">
    <a href="/impressum.html">Impressum</a> · <a href="/datenschutz.html">Datenschutz</a> · <a href="/agb.html">Nutzungsbedingungen</a> · <a href="/">Startseite</a>
  </div>
</footer>

<script>window.__PROFIL = <?= $entry ? json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null' ?>;</script>
<script src="/data.js"></script>
<script>
(async () => {
  const e = window.__PROFIL || null;

  if(!e){
    document.getElementById("fehler").hidden = false;
    return;
  }
  // Foto oder Platzhalter („Foto folgt")
  const photo = document.getElementById("p-photo");
  if(e.foto){
    photo.innerHTML = `<img src="${esc(e.foto)}" alt="Foto von ${esc(e.name)}">`;
  }else{
    photo.style.background = avColor(e.name);
    photo.innerHTML = `<span class="ini">${esc(e.name.trim()[0] || "?")}</span><span class="hint">📷 Foto folgt</span>`;
  }
  document.getElementById("p-name").textContent = e.name;
  document.getElementById("p-ort").textContent = "📍 " + e.ort + (e.bundesland ? " · " + e.bundesland : "");
  document.getElementById("p-badges").innerHTML =
    badgePlaetze(e.plaetze) +
    (e.erlaubnis ? '<span class="badge b-check" title="Pflegeerlaubnis vom Jugendamt nach § 43 SGB VIII">✓ Pflegeerlaubnis §43</span>' : "") +
    frischeBadge(e);
  document.getElementById("p-meta").innerHTML =
    `<span>🕐 <b>${esc(e.zeiten)}</b></span><span class="chips">${e.alter.map(a => `<span class="chip">${esc(a)}</span>`).join("")}</span>`
    + (e.extras && e.extras.length ? `<span class="chips">${e.extras.map(x => `<span class="chip">✓ ${esc(x)}</span>`).join("")}</span>` : "");
  document.getElementById("p-text").textContent = e.persoenlich || "Für dieses Profil wurde noch kein persönlicher Text hinterlegt.";

  // "Auf einen Blick" – strukturierte Details
  const details = [];
  if(e.frei_ab)       details.push(['🗓️','Plätze frei ab', e.frei_ab]);
  if(e.qualifikation) details.push(['🎓','Qualifikation', e.qualifikation]);
  if(e.sprachen)      details.push(['🗣️','Sprachen', e.sprachen]);
  if(e.ernaehrung)    details.push(['🍎','Ernährung', e.ernaehrung]);
  if(e.haustiere)     details.push(['🐾','Haustiere', e.haustiere]);
  if(e.nichtraucher)  details.push(['🚭','Haushalt','Nichtraucher']);
  const jahr = String(e.created_at || "").slice(0,4);
  if(jahr >= "2020" && jahr <= "2099") details.push(['🗓️','Mitglied seit', jahr]);
  const akt = zeitRelativ(e.updated_at || e.created_at);
  if(akt) details.push(['🔄','Zuletzt aktualisiert', akt]);
  if(details.length){
    document.getElementById("p-details").innerHTML =
      '<h2 class="p-label">Auf einen Blick</h2><div class="detail-grid">' +
      details.map(([ic,l,v]) => `<div class="detail-item"><span class="di-ic">${ic}</span><div><span class="di-label">${esc(l)}</span><span class="di-val">${esc(v)}</span></div></div>`).join('') +
      '</div>';
    document.getElementById("p-details").hidden = false;
  }
  // Pädagogischer Schwerpunkt
  if(e.konzept){
    const k = document.getElementById("p-konzept");
    k.innerHTML = `<h2 class="p-label">Pädagogischer Schwerpunkt</h2><p class="p-text">${esc(e.konzept)}</p>`;
    k.hidden = false;
  }
  document.getElementById("p-contact").innerHTML =
    `<a class="btn btn-coral" href="mailto:${esc(e.email)}">✉️ E-Mail schreiben</a>` +
    (e.tel ? `<a class="btn btn-ghost" href="tel:${esc(e.tel.replace(/\s/g,""))}">📞 ${esc(e.tel)}</a>` : "") +
    (e.tel && istHandy(e.tel) ? `<a class="btn btn-whatsapp" href="https://wa.me/${waNummer(e.tel)}?text=${encodeURIComponent("Hallo " + e.name + ", ich habe Ihr Profil auf mein Tageskind gesehen und suche einen Betreuungsplatz für mein Kind.")}" target="_blank" rel="noopener">💬 WhatsApp</a>` : "");

  // Bilder-Wechsel + Vollbild-Lightbox: Profilbild zuerst, dann die Galerie-Bilder
  const alleBilder = [];
  if(e.foto) alleBilder.push(e.foto);
  if(Array.isArray(e.fotos)) alleBilder.push(...e.fotos);

  function oeffneLightbox(start){
    if(!alleBilder.length) return;
    let i = start;
    const lb = document.createElement("div");
    lb.className = "lightbox open";
    lb.innerHTML = `<button class="lb-close" aria-label="Schließen">✕</button>` +
      (alleBilder.length > 1 ? `<button class="lb-nav lb-prev" aria-label="Vorheriges Bild">‹</button><button class="lb-nav lb-next" aria-label="Nächstes Bild">›</button>` : "") +
      `<img src="${esc(alleBilder[i])}" alt="Bild ${i+1} von ${esc(e.name)}">`;
    document.body.appendChild(lb);
    document.body.style.overflow = "hidden";
    const img = lb.querySelector("img");
    const zeige = n => { i = (n + alleBilder.length) % alleBilder.length; img.src = alleBilder[i]; };
    const schliesse = () => { lb.classList.remove("open"); document.body.style.overflow = ""; document.removeEventListener("keydown", taste); setTimeout(() => lb.remove(), 200); };
    const taste = ev => { if(ev.key === "Escape") schliesse(); else if(ev.key === "ArrowLeft") zeige(i - 1); else if(ev.key === "ArrowRight") zeige(i + 1); };
    lb.addEventListener("click", ev => {
      if(ev.target.classList.contains("lb-prev")){ ev.stopPropagation(); zeige(i - 1); }
      else if(ev.target.classList.contains("lb-next")){ ev.stopPropagation(); zeige(i + 1); }
      else if(ev.target === lb || ev.target.classList.contains("lb-close")) schliesse();
    });
    document.addEventListener("keydown", taste);
  }
  if(e.foto) document.getElementById("p-photo").addEventListener("click", () => oeffneLightbox(0));

  if(alleBilder.length > 1){
    const haupt = document.getElementById("p-gal-haupt");
    const thumbs = document.getElementById("p-gal-thumbs");
    let aktIdx = 0;
    const setAktiv = i => { aktIdx = i; haupt.src = alleBilder[i]; [...thumbs.children].forEach((x,j) => x.classList.toggle("aktiv", i===j)); };
    thumbs.innerHTML = alleBilder.map((f,i) => `<img src="${esc(f)}" alt="Bild ${i+1}" data-i="${i}">`).join("");
    thumbs.addEventListener("click", ev => { const t = ev.target.closest("img[data-i]"); if(t) setAktiv(+t.dataset.i); });
    haupt.parentElement.addEventListener("click", () => oeffneLightbox(aktIdx));
    setAktiv(0);
    document.getElementById("p-galerie").hidden = false;
    let idx = 0;
    setInterval(() => { idx = (idx + 1) % alleBilder.length; setAktiv(idx); }, 4500);
  }

  // Merken + Teilen
  const url = location.href;
  const shareTxt = `Tagesmutter ${e.name} in ${e.ort} – Kindertagespflege`;
  document.getElementById("p-share").innerHTML =
    `<button class="btn btn-ghost" id="p-fav" type="button">${favHas(e.id)?'❤️ Gemerkt':'🤍 Merken'}</button>` +
    `<a class="btn btn-ghost" href="https://wa.me/?text=${encodeURIComponent(shareTxt + ' ' + url)}" target="_blank" rel="noopener">💬 Teilen</a>` +
    `<button class="btn btn-ghost" id="p-copy" type="button">🔗 Link kopieren</button>`;
  document.getElementById("p-fav").addEventListener("click", () => {
    const on = favToggle(e.id);
    document.getElementById("p-fav").textContent = on ? "❤️ Gemerkt" : "🤍 Merken";
  });
  document.getElementById("p-copy").addEventListener("click", async () => {
    const b = document.getElementById("p-copy");
    try{ await navigator.clipboard.writeText(url); b.textContent = "✓ Kopiert!"; setTimeout(() => b.textContent = "🔗 Link kopieren", 1600); }
    catch(err){ b.textContent = "Bitte manuell kopieren"; }
  });

  // Anfrage-Formular aktivieren
  document.getElementById("p-anfrage").hidden = false;
  // Nachrichten-Bausteine (Punkt 4): fügen Text ins Nachrichtenfeld ein
  document.getElementById("a-bausteine").addEventListener("click", ev => {
    const b = ev.target.closest("button[data-b]");
    if(!b) return;
    const ta = document.getElementById("a-text");
    ta.value = (ta.value.trim() ? ta.value.trim() + " " : "") + b.dataset.b;
    ta.focus();
  });
  document.getElementById("anfrage-form").addEventListener("submit", async ev => {
    ev.preventDefault();
    const form = ev.target;
    if(!form.reportValidity()) return;
    const fd = new FormData();
    fd.append("id", e.id);
    fd.append("name", document.getElementById("a-name").value.trim());
    fd.append("email", document.getElementById("a-email").value.trim());
    fd.append("tel", document.getElementById("a-tel").value.trim());
    fd.append("nachricht", document.getElementById("a-text").value.trim());
    fd.append("firma_website", document.getElementById("a-hp").value);
    const btn = form.querySelector("button");
    btn.disabled = true; btn.classList.add("loading");
    const msg = document.getElementById("anfrage-msg");
    try{
      const res = await fetch("api/anfrage.php", {method:"POST", body:fd});
      const data = await res.json().catch(() => ({}));
      if(!res.ok || !data.ok) throw new Error(data.error || "Senden fehlgeschlagen");
      form.reset();
      msg.innerHTML = '<div class="auth-ok">✅ Deine Anfrage wurde gesendet! Die Tagesmutter meldet sich bei dir.</div>';
      msg.scrollIntoView({behavior:"smooth", block:"center"});
    }catch(err){
      msg.innerHTML = `<div class="auth-err">${err.message.replace(/</g,"&lt;")}</div>`;
    }finally{ btn.disabled = false; btn.classList.remove("loading"); }
  });

  // ---------- Vormerkung (nur wenn kein Platz frei) ----------
  if(e.plaetze === 0){
    document.getElementById("p-vormerkung").hidden = false;
    document.getElementById("vm-form").addEventListener("submit", async ev => {
      ev.preventDefault();
      const form = ev.target;
      if(!form.reportValidity()) return;
      const fd = new FormData();
      fd.append("id", e.id);
      fd.append("name", document.getElementById("vm-name").value.trim());
      fd.append("email", document.getElementById("vm-email").value.trim());
      fd.append("firma_website", document.getElementById("vm-hp").value);
      const btn = form.querySelector("button"); btn.disabled = true; btn.classList.add("loading");
      try{
        const res = await fetch("api/vormerkung.php", {method:"POST", body:fd});
        const data = await res.json().catch(() => ({}));
        if(!res.ok || !data.ok) throw new Error(data.error || "Fehler");
        form.reset();
        document.getElementById("vm-msg").innerHTML = '<div class="auth-ok">✅ Du bist vorgemerkt! Du wirst benachrichtigt, sobald ein Platz frei wird.</div>';
      }catch(err){ document.getElementById("vm-msg").innerHTML = `<div class="auth-err">${err.message.replace(/</g,"&lt;")}</div>`; }
      finally{ btn.disabled = false; btn.classList.remove("loading"); }
    });
  }

  // ---------- Ähnliche Tagesmütter ----------
  try{
    const alle = await ladeEintraege();
    const aehnlich = alle.filter(x => x.id !== e.id && (x.ort === e.ort || x.bundesland === e.bundesland)).slice(0, 3);
    if(aehnlich.length){
      document.getElementById("p-aehnliche").innerHTML =
        `<h2 class="p-label">Weitere Tagesmütter in der Nähe</h2><div class="aehnliche-grid">` +
        aehnlich.map(x => `<a class="ae-card" href="${profilUrl(x.id)}"><h4>${esc(x.name)}</h4><div class="ae-ort">📍 ${esc(x.ort)}</div><div style="margin-top:.4rem">${badgePlaetze(x.plaetze)}</div></a>`).join("") +
        `</div>`;
      document.getElementById("p-aehnliche").hidden = false;
    }
  }catch(err){}

  // Schwebender Kontakt-Button (mobil) – scrollt zum Anfrageformular, blendet sich dort aus
  const mcta = document.createElement("a");
  mcta.className = "mobile-cta";
  mcta.href = "#p-anfrage";
  mcta.textContent = "✉️ Kontakt aufnehmen";
  mcta.style.transition = "opacity .25s";
  document.body.appendChild(mcta);
  mcta.addEventListener("click", ev => {
    ev.preventDefault();
    const ziel = document.getElementById("a-name");
    ziel.scrollIntoView({behavior:"smooth", block:"center"});
    setTimeout(() => ziel.focus(), 450);
  });
  new IntersectionObserver(es => {
    const sicht = es[0].isIntersecting;
    mcta.style.opacity = sicht ? "0" : "1";
    mcta.style.pointerEvents = sicht ? "none" : "auto";
  }, {threshold:.2}).observe(document.getElementById("p-anfrage"));

  document.getElementById("profil").hidden = false;
})();

// Header-Schatten beim Scrollen
const headerEl = document.getElementById("header");
addEventListener("scroll", () => headerEl.classList.toggle("scrolled", scrollY > 8), {passive:true});
</script>
</body>
</html>

<?php
/**
 * Stadt-Landingpage (SEO) – server-seitig gerendert.
 * URL: /tagesmutter/<slug>  (per .htaccess → stadt.php?stadt=<slug>)
 * Ziel: für "Tagesmutter <Stadt>" ranken. Inhalte stehen im HTML-Quelltext
 * (nicht per JS), damit Google + KI sie sehen.
 */
declare(strict_types=1);
require __DIR__ . '/api/db.php';

$slug  = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['stadt'] ?? '')));
$stadt = $slug ? tmf_stadt_von_slug($slug) : null;

// Unbekannte Stadt → 404
if ($stadt === null) {
    http_response_code(404);
    $stadt = null;
}

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$eintraege = [];
$andereOrte = [];
if ($stadt !== null) {
    try {
        $pdo = tmf_db();
        $st = $pdo->prepare("SELECT * FROM tagesmuetter WHERE ort = ? AND status = 'approved'
                             ORDER BY (plaetze > 0) DESC, plaetze DESC, created_at DESC");
        $st->execute([$stadt]);
        $eintraege = array_map('tmf_row_to_entry', $st->fetchAll());
        // andere Städte mit Angeboten (für "in der Nähe")
        $q = $pdo->prepare("SELECT ort, COUNT(*) c FROM tagesmuetter WHERE status='approved' AND ort <> ? GROUP BY ort ORDER BY c DESC LIMIT 8");
        $q->execute([$stadt]);
        $andereOrte = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) { /* leer ausliefern */ }
}

$anzahl   = count($eintraege);
$freie    = array_sum(array_map(fn($x) => max(0, (int)$x['plaetze']), $eintraege));
$base     = 'https://mein-tageskind.de';
$canon    = $base . '/tagesmutter/' . $slug;
// SEO: Nur Stadt-Seiten MIT mindestens einer Tagesmutter indexieren. Leere Städte
// bleiben erreichbar (Kaltstart-Einladung), aber noindex – so entstehen bei 138 BW-Städten
// keine dünnen/doorway-Seiten im Index; sobald eine TM eingetragen ist, wird die Seite indexiert.
$robots   = ($stadt !== null && $anzahl > 0) ? 'index, follow' : 'noindex, follow';
$titel    = $stadt ? "Tagesmutter {$stadt} finden – freie Plätze in der Kindertagespflege" : 'Stadt nicht gefunden';
$desc     = $stadt
    ? ($anzahl > 0
        ? "{$anzahl} Tagesmütter in {$stadt}: freie Plätze, Betreuungszeiten, Qualifikationen und direkter Kontakt. Kindertagespflege – kostenlos und mit Pflegeerlaubnis (§ 43 SGB VIII)."
        : "Tagesmütter in {$stadt} (Kindertagespflege): Angebote, freie Plätze und direkter Kontakt. Trag dich als Tagesmutter kostenlos ein.")
    : 'Diese Stadt-Seite existiert nicht.';

// ---- strukturierte Daten (schema.org) ----
$schema = null;
if ($stadt !== null) {
    $items = [];
    foreach ($eintraege as $i => $x) {
        $items[] = [
            '@type' => 'ListItem', 'position' => $i + 1,
            'item' => [
                '@type' => 'ChildCare',
                'name'  => $x['name'],
                'url'   => $base . '/profil/' . rawurlencode($x['id']),
                'areaServed' => $stadt,
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => $stadt, 'addressRegion' => TMF_REGION, 'addressCountry' => 'DE'],
            ],
        ];
    }
    $schema = [
        '@context' => 'https://schema.org', '@type' => 'CollectionPage',
        'name' => "Tagesmütter in {$stadt}", 'url' => $canon,
        'about' => 'Kindertagespflege / Tagesmutter in ' . $stadt,
        'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tagesmütter', 'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $stadt, 'item' => $canon],
        ]],
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $anzahl, 'itemListElement' => $items],
    ];
}

// ---- Karten-Rendering (wie Startseite, aber server-seitig) ----
function stadt_karte(array $x, callable $e): string {
    $farben = ['#f2a25c','#6aa87e','#7f9fd1','#d17fa8','#a58bd1','#5cbdb9'];
    $farbe  = $farben[array_sum(array_map('ord', str_split($x['name']))) % count($farben)];
    $ini    = mb_strtoupper(mb_substr(trim($x['name']), 0, 1)) ?: '?';
    $p      = (int)$x['plaetze'];
    $pBadge = $p >= 2 ? "🟢 {$p} Plätze frei" : ($p === 1 ? '🟢 1 Platz frei' : 'Warteliste');
    $pClass = $p > 0 ? 'b-frei' : 'b-voll';
    $teaser = mb_substr((string)$x['persoenlich'], 0, 140);
    if (mb_strlen((string)$x['persoenlich']) > 140) $teaser = rtrim($teaser) . ' …';
    $url    = '/profil/' . rawurlencode($x['id']);
    $avatar = $x['foto']
        ? '<div class="avatar"><img src="' . $e($x['foto']) . '" alt="Foto von ' . $e($x['name']) . '" loading="lazy"></div>'
        : '<div class="avatar" style="background:' . $farbe . '">' . $e($ini) . '</div>';
    $alterChips = '';
    foreach ((array)$x['alter'] as $a) $alterChips .= '<span class="chip">' . $e($a) . '</span>';
    $h  = '<article class="card">';
    $h .= '<div class="card-top">' . $avatar . '<div><h3>' . $e($x['name']) . '</h3>';
    $h .= '<div class="ort">📍 ' . $e($x['ort']) . '</div></div></div>';
    $h .= '<div class="badges"><span class="badge ' . $pClass . '">' . $pBadge . '</span>';
    if ($x['erlaubnis']) $h .= '<span class="badge b-check" title="Pflegeerlaubnis nach § 43 SGB VIII">✓ Pflegeerlaubnis §43</span>';
    $h .= '</div>';
    if ($teaser) $h .= '<p class="desc">' . $e($teaser) . '</p>';
    $h .= '<div class="meta"><span>🕐 <b>' . $e($x['zeiten']) . '</b></span>';
    if (!empty($x['frei_ab'])) $h .= '<span>🗓️ frei ab <b>' . $e($x['frei_ab']) . '</b></span>';
    $h .= '<span class="chips">' . $alterChips . '</span></div>';
    $h .= '<div class="contact"><a class="btn btn-coral" href="' . $url . '">👤 Profil ansehen</a>';
    $h .= '<a class="btn btn-ghost" href="mailto:' . $e($x['email']) . '">✉️ Kontakt</a></div>';
    return $h . '</article>';
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
<meta property="og:type" content="website">
<meta property="og:site_name" content="mein Tageskind">
<meta property="og:title" content="<?= $e($titel) ?>">
<meta property="og:description" content="<?= $e($desc) ?>">
<meta property="og:url" content="<?= $e($canon) ?>">
<title><?= $e($titel) ?> | mein Tageskind</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="/styles.css">
<?php if ($schema): ?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<style>
  .stadt-main{max-width:1120px;margin:0 auto;padding:1.6rem 1.4rem 3.5rem}
  .crumb{font-size:.85rem;color:var(--muted);font-weight:700;margin-bottom:1rem}
  .crumb a{color:var(--muted);text-decoration:none}
  .crumb a:hover{color:var(--coral)}
  .stadt-head h1{font-size:clamp(1.7rem,4vw,2.5rem);font-weight:800;letter-spacing:-.02em;line-height:1.15}
  .stadt-head .intro{color:var(--ink-soft);font-size:1.02rem;max-width:60ch;margin-top:.7rem}
  .stadt-stats{display:flex;gap:1.4rem;flex-wrap:wrap;margin:1.1rem 0 .3rem;color:var(--ink-soft);font-size:.9rem;font-weight:700}
  .stadt-stats b{color:var(--coral)}
  .andere{margin-top:2.4rem;padding-top:1.6rem;border-top:1px solid var(--line)}
  .andere h2{font-size:1.15rem;font-weight:800;margin-bottom:.7rem}
  .andere-list{display:flex;flex-wrap:wrap;gap:.5rem}
  .andere-list a{background:var(--card);border:1.5px solid var(--line);border-radius:999px;padding:.4rem .9rem;font-size:.88rem;font-weight:700;text-decoration:none;color:var(--ink);box-shadow:var(--shadow-sm)}
  .andere-list a:hover{border-color:var(--coral);color:var(--coral-dark)}
  .leerbox{background:var(--card);border:1px solid var(--line);border-radius:var(--radius);padding:2rem 1.4rem;text-align:center;box-shadow:var(--shadow-sm)}
  .leerbox .emo{font-size:2.6rem;display:block;margin-bottom:.5rem}
  /* Tagesmutter-Werbung (leere Stadt – Kaltstart) */
  .tm-werbung{background:var(--coral-soft);border:1px solid var(--line);border-radius:18px;padding:2.2rem 1.6rem;text-align:center;margin-top:1.6rem}
  .tmw-emo{font-size:2.8rem;margin-bottom:.3rem}
  .tm-werbung h2{font-size:clamp(1.3rem,3vw,1.7rem);font-weight:800;letter-spacing:-.01em}
  .tmw-lead{color:var(--ink-soft);max-width:52ch;margin:.7rem auto 1.1rem;font-size:1rem}
  .tmw-vorteile{list-style:none;display:inline-flex;flex-direction:column;gap:.45rem;text-align:left;margin:0 auto 1.4rem;padding:0}
  .tmw-vorteile li{font-weight:700;font-size:.95rem;color:var(--ink)}
  .tmw-vorteile li::before{content:"✓ ";color:var(--sage-dark);font-weight:900}
  .tmw-cta{font-size:1.05rem;padding:.85rem 2rem}
  .tmw-klein{margin-top:.9rem;font-size:.88rem;color:var(--muted)}
  .tmw-klein a{color:var(--coral);font-weight:800;text-decoration:none}
  .stadt-info-eltern{color:var(--muted);font-size:.92rem;text-align:center;margin:1rem auto 0;max-width:54ch}
  /* Info-Abschnitt (Content-Wert) */
  .stadt-wissen{margin-top:2.4rem;padding-top:1.6rem;border-top:1px solid var(--line)}
  .stadt-wissen h2{font-size:1.2rem;font-weight:800;margin-bottom:.7rem}
  .stadt-wissen p{color:var(--ink-soft);font-size:.95rem;margin-bottom:.7rem;max-width:70ch}
  .stadt-wissen a{color:var(--coral);font-weight:700}
</style>
</head>
<body>
<a href="#inhalt" class="skip-link">Zum Inhalt springen</a>

<header id="header">
  <div class="header-inner">
    <a class="logo" href="/"><img src="/img/logo-mein-tageskind.png" alt="mein Tageskind" class="logo-img"></a>
    <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Menü öffnen" aria-expanded="false" aria-controls="hauptnav">
      <span></span><span></span><span></span>
    </button>
    <nav id="hauptnav">
      <a href="/#liste">Tagesmütter</a>
      <a href="/#so-gehts">So funktioniert’s</a>
      <a href="/#kosten">Kosten</a>
      
      <a href="/login.php">Anmelden</a>
      <a href="/registrieren.php" class="cta">Als Tagesmutter eintragen</a>
    </nav>
  </div>
</header>

<main class="stadt-main" id="inhalt">
<?php if ($stadt === null): ?>
  <div class="leerbox">
    <span class="emo">🧸</span>
    <h1>Stadt nicht gefunden</h1>
    <p style="color:var(--ink-soft);margin:.6rem 0 1.3rem">Diese Stadt-Seite gibt es nicht. Schau in der Gesamtübersicht.</p>
    <a class="btn btn-coral" href="/#liste">Zur Übersicht aller Tagesmütter</a>
  </div>
<?php else: ?>
  <nav class="crumb" aria-label="Brotkrumen">
    <a href="/">Startseite</a> › <a href="/#liste">Tagesmütter</a> › <span><?= $e($stadt) ?></span>
  </nav>

  <div class="stadt-head">
    <h1>Tagesmütter in <?= $e($stadt) ?></h1>
    <p class="intro">
      Du suchst eine <strong>Tagesmutter in <?= $e($stadt) ?></strong>? Hier findest du Angebote der Kindertagespflege
      <?= $anzahl > 0 ? 'mit freien Plätzen, Betreuungszeiten und Qualifikationen' : 'in deiner Region' ?> –
      inklusive direktem Kontakt. Alle gelisteten Tagesmütter haben eine Pflegeerlaubnis nach § 43 SGB VIII.
      Die Nutzung ist für Eltern kostenlos.
    </p>
    <?php if ($anzahl > 0): ?>
    <div class="stadt-stats">
      <span><b><?= $anzahl ?></b> <?= $anzahl === 1 ? 'Tagesmutter' : 'Tagesmütter' ?> in <?= $e($stadt) ?></span>
      <span><b><?= $freie ?></b> freie Plätze</span>
      <span>💶 <b>0&nbsp;€</b> für Eltern</span>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($anzahl > 0): ?>
    <div class="grid" style="margin-top:1.6rem">
      <?php foreach ($eintraege as $x) echo stadt_karte($x, $e); ?>
    </div>
  <?php else: ?>
    <div class="tm-werbung">
      <div class="tmw-emo">🌟</div>
      <h2>Bist du Tagesmutter in <?= $e($stadt) ?>?</h2>
      <p class="tmw-lead">Sei die <b>Erste</b> in <?= $e($stadt) ?>! Trag dich kostenlos ein und werde von Eltern in deiner Nähe sofort gefunden – so füllst du deine freien Plätze schneller und ganz ohne Aufwand.</p>
      <ul class="tmw-vorteile">
        <li>100&nbsp;% kostenlos &amp; unverbindlich</li>
        <li>Eltern finden dich direkt – auch über Google</li>
        <li>In 2 Minuten eingetragen, jederzeit änderbar</li>
      </ul>
      <a class="btn btn-coral tmw-cta" href="/registrieren.php">Jetzt kostenlos eintragen →</a>
      <p class="tmw-klein">Schon dabei? <a href="/login.php">Hier anmelden</a></p>
    </div>
    <p class="stadt-info-eltern">Du suchst als <b>Elternteil</b>? Aktuell ist noch keine Tagesmutter in <?= $e($stadt) ?> gelistet – das Portal ist neu und wächst stetig. Schau bei den Tagesmüttern in der Nähe vorbei oder komm bald wieder.</p>
  <?php endif; ?>

  <?php if ($andereOrte): ?>
    <section class="andere">
      <h2>Tagesmütter in der Nähe</h2>
      <div class="andere-list">
        <?php foreach ($andereOrte as $o): ?>
          <a href="/tagesmutter/<?= $e(tmf_slug($o['ort'])) ?>">📍 <?= $e($o['ort']) ?> <span style="color:var(--muted)">(<?= (int)$o['c'] ?>)</span></a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <section class="stadt-wissen">
    <h2>Kindertagespflege in <?= $e($stadt) ?> – kurz erklärt</h2>
    <p>Eine <b>Tagesmutter</b> (oder ein Tagesvater) betreut Kinder – meist im Alter von null bis drei Jahren – in einer kleinen, familiären Gruppe von höchstens fünf Kindern, in der Regel im eigenen Zuhause. Kindertagespflege ist eine anerkannte, oft flexiblere Alternative zur Kita und wird vom Jugendamt gefördert.</p>
    <p>Jede Tagespflegeperson benötigt eine <b>Pflegeerlaubnis nach § 43 SGB VIII</b>, die das Jugendamt nach Qualifikationsnachweis und Eignungsprüfung erteilt. Die Kosten werden – wie beim Kita-Platz – überwiegend öffentlich gefördert; für Eltern bleibt meist nur ein einkommensabhängiger Eigenanteil.</p>
    <p>Über „mein Tageskind“ siehst du die Angebote in <?= $e($stadt) ?> auf einen Blick und nimmst direkt Kontakt auf – kostenlos und ohne Anmeldung. Du bist selbst Tagespflegeperson? <a href="/registrieren.php">Trag dich kostenlos ein</a>.</p>
  </section>
<?php endif; ?>
</main>

<footer>
  <div class="footer-grid">
    <div>
      <a class="logo" href="/"><img src="/img/logo-mein-tageskind.png" alt="mein Tageskind" class="logo-img" style="height:50px"></a>
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
        <li><a href="/registrieren.php">Kostenlos eintragen</a></li>
        <li><a href="/impressum.html">Impressum</a></li>
        <li><a href="/datenschutz.html">Datenschutz</a></li>
      </ul>
    </div>
  </div>
  <div class="powered">
    <span class="pw-label">Powered by</span>
    <a class="pw-gaseit" href="https://gaseit.de" target="_blank" rel="noopener" aria-label="Gaseit GmbH">
      <img src="/img/gaseit-logo.png" alt="Gaseit GmbH" class="pw-gaseit-img">
    </a>
  </div>
  <div class="footer-bottom">
    <a href="/impressum.html">Impressum</a> · <a href="/datenschutz.html">Datenschutz</a> · <a href="/agb.html">Nutzungsbedingungen</a> · <a href="/">Startseite</a>
  </div>
</footer>

<script src="/data.js"></script>
</body>
</html>

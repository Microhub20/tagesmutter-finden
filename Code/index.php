<?php
/**
 * Startseite – server-seitig gerendert (SEO).
 * Die Tagesmutter-Liste, Statistiken und schema.org stehen im HTML-Quelltext
 * (nicht erst per JS), damit Google + KI-Crawler sie sehen. data.js übernimmt danach
 * Filter/Suche/Merkliste und rendert aus dem eingebetteten window.__ALLE (kein fetch,
 * kein Skeleton-Flackern).
 */
declare(strict_types=1);
require __DIR__ . '/api/db.php';

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$eintraege = [];
try {
    $pdo  = tmf_db();
    $rows = $pdo->query(
        "SELECT * FROM tagesmuetter WHERE status = 'approved'
         ORDER BY (plaetze > 0) DESC, plaetze DESC, created_at DESC"
    )->fetchAll();
    $eintraege = array_map('tmf_row_to_entry', $rows);
} catch (Throwable $ex) { $eintraege = []; }

$anzahl = count($eintraege);
$freie  = array_sum(array_map(fn($x) => max(0, (int)$x['plaetze']), $eintraege));
$orte   = count(array_unique(array_map(fn($x) => $x['ort'], $eintraege)));
$base   = 'https://tagesmutter-vergleich.de';

// Karten-Rendering server-seitig (gleiche Struktur wie data.js karteHtml – den Merken-
// Button ergänzt das JS beim Neurendern; hier zählt der crawlbare Inhalt).
function idx_karte(array $x, callable $e): string {
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
    $h  = '<article class="card" data-id="' . $e($x['id']) . '" tabindex="0" role="link" aria-label="Profilseite von ' . $e($x['name']) . ' öffnen">';
    $h .= '<div class="card-top">' . $avatar . '<div><h3>' . $e($x['name']) . '</h3>';
    $h .= '<div class="ort">📍 ' . $e($x['ort']) . ($x['bundesland'] ? ' <span class="bl">· ' . $e($x['bundesland']) . '</span>' : '') . '</div></div></div>';
    $h .= '<div class="badges"><span class="badge ' . $pClass . '">' . $pBadge . '</span>';
    if ($x['erlaubnis']) $h .= '<span class="badge b-check" title="Pflegeerlaubnis vom Jugendamt nach § 43 SGB VIII">✓ Pflegeerlaubnis §43</span>';
    $h .= '</div>';
    if ($teaser) $h .= '<p class="desc">' . $e($teaser) . '</p>';
    $h .= '<div class="meta"><span>🕐 <b>' . $e($x['zeiten']) . '</b></span>';
    if (!empty($x['frei_ab'])) $h .= '<span>🗓️ frei ab <b>' . $e($x['frei_ab']) . '</b></span>';
    $h .= '<span class="chips">' . $alterChips . '</span></div>';
    $h .= '<div class="contact"><a class="btn btn-coral" href="' . $url . '">👤 Profil ansehen</a>';
    $h .= '<a class="btn btn-ghost" href="mailto:' . $e($x['email']) . '">✉️ Kontakt</a></div>';
    return $h . '</article>';
}

// schema.org ItemList der aktuell gelisteten Tagesmütter (zusätzlich zur WebSite unten)
$itemList = null;
if ($anzahl > 0) {
    $items = [];
    foreach ($eintraege as $i => $x) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'item' => [
            '@type' => 'ChildCare', 'name' => $x['name'],
            'url' => $base . '/profil/' . rawurlencode($x['id']),
            'areaServed' => $x['ort'],
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => $x['ort'], 'addressRegion' => ($x['bundesland'] ?: TMF_REGION), 'addressCountry' => 'DE'],
        ]];
    }
    $itemList = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Tagesmütter in ' . TMF_REGION, 'numberOfItems' => $anzahl, 'itemListElement' => $items];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="index, follow">
<meta name="description" content="Tagesmütter in deiner Region auf einen Blick: freie Plätze, Betreuungszeiten, direkter Kontakt. Kostenlos für Eltern und Tagesmütter.">
<meta name="author" content="Gaseit GmbH">
<link rel="canonical" href="https://tagesmutter-vergleich.de/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Tagesmutter finden">
<meta property="og:title" content="Tagesmutter finden – Kindertagespflege in deiner Nähe">
<meta property="og:description" content="Alle Tagesmütter deiner Region auf einen Blick: freie Plätze, Betreuungszeiten, direkter Kontakt. Kostenlos.">
<meta property="og:image" content="https://tagesmutter-vergleich.de/img/hero.jpg">
<meta property="og:url" content="https://tagesmutter-vergleich.de/">
<meta name="twitter:card" content="summary_large_image">
<title>Tagesmutter finden – Kindertagespflege in deiner Nähe</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="styles.css">
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"WebSite","name":"Tagesmutter finden","url":"https://tagesmutter-vergleich.de/","description":"Verzeichnis für Kindertagespflege – Tagesmütter mit freien Plätzen in deiner Region finden.","publisher":{"@type":"Organization","name":"Gaseit GmbH","url":"https://gaseit.de"}}
</script>
<?php if ($itemList): ?>
<script type="application/ld+json"><?= json_encode($itemList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"Organization","name":"Gaseit GmbH","url":"https://gaseit.de","description":"Betreiber des Portals „Tagesmutter finden“ – Verzeichnis für Kindertagespflege.","address":{"@type":"PostalAddress","streetAddress":"Gymnasiumstr. 12","postalCode":"72336","addressLocality":"Balingen","addressCountry":"DE"},"email":"info@gaseit.de","sameAs":["https://gaseit.de"]}
</script>
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":"Was kostet die Nutzung des Portals?","acceptedAnswer":{"@type":"Answer","text":"Nichts – weder für Eltern noch für Tagesmütter. Das Portal finanziert sich über dezente Werbeanzeigen am Seitenrand."}},{"@type":"Question","name":"Was ist Kindertagespflege eigentlich?","acceptedAnswer":{"@type":"Answer","text":"Die Betreuung von Kindern (meist 0–3 Jahre) durch qualifizierte Tagesmütter oder Tagesväter – in kleinen Gruppen von maximal fünf Kindern, in der Regel im Zuhause der Betreuungsperson. Sie ist gesetzlich anerkannt und wird vom Landratsamt gefördert wie ein Kita-Platz."}},{"@type":"Question","name":"Wie werden die Profile geprüft?","acceptedAnswer":{"@type":"Answer","text":"Jede Tagesmutter trägt sich selbst ein und willigt in die Veröffentlichung ein. Vor der Freischaltung prüfen wir die Angaben – insbesondere die Pflegeerlaubnis nach § 43 SGB VIII, die das Jugendamt nach Qualifikationsnachweis und Eignungsprüfung erteilt."}},{"@type":"Question","name":"Wie bekomme ich einen Betreuungsplatz?","acceptedAnswer":{"@type":"Answer","text":"Tagesmutter mit freien Plätzen suchen, direkt Kontakt aufnehmen und einen Kennenlerntermin vereinbaren. Die Kostenübernahme bzw. Förderung läuft anschließend über das zuständige Jugendamt – die Tagesmutter kennt den Ablauf und hilft dabei."}}]}
</script>
</head>
<body>
<a href="#liste" class="skip-link">Zum Inhalt springen</a>

<header id="header">
  <div class="header-inner">
    <a class="logo" href="/">
      <img src="img/logo-tagesmutter.png" alt="Tagesmutter finden – Kindertagespflege" class="logo-img">
    </a>
    <div class="header-search">
      <span class="hs-icon" aria-hidden="true">🔎</span>
      <input id="search" type="search" placeholder="Name oder Ort suchen …" aria-label="Nach Name oder Ort suchen">
    </div>
    <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Menü öffnen" aria-expanded="false" aria-controls="hauptnav">
      <span></span><span></span><span></span>
    </button>
    <nav id="hauptnav">
      <a href="#liste">Tagesmütter</a>
      <a href="#so-gehts">So funktioniert’s</a>
      <a href="#kosten">Kosten</a>
      <a href="#faq">FAQ</a>
      <a href="login.php">Anmelden</a>
      <a href="registrieren.php" class="cta">Als Tagesmutter eintragen</a>
    </nav>
  </div>
</header>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="eyebrow">💛 Kindertagespflege in deiner Region</span>
      <h1>Die passende <em>Tagesmutter</em> für dein Kind – ganz in deiner Nähe</h1>
      <p class="lead">Alle Betreuungsangebote deiner Stadt auf einen Blick: freie Plätze, Zeiten, Qualifikationen – und der direkte Draht zur Tagesmutter. Kostenlos und ohne Anmeldung.</p>
      <div class="hero-stadt">
        <label for="f-ort" class="hs-label">📍 In welcher Stadt suchst du?</label>
        <div class="stadt-wrap">
          <select id="f-ort" class="stadt-select" aria-label="Stadt oder Gemeinde wählen"></select>
          <button class="stadt-go" type="button" id="stadt-go">Finden</button>
        </div>
      </div>
      <div class="trust">
        <span>✅ <b>Geprüfte Profile</b>&nbsp;(§ 43 SGB VIII)</span>
        <span>💶 <b>100 % kostenlos</b></span>
        <span>📍 <b>In deiner Region</b></span>
      </div>
    </div>
    <div class="hero-visual">
      <div class="frame"><img src="img/hero.jpg" alt="Illustration: Tagesmutter liest zwei Kindern ein Bilderbuch vor" width="1600" height="900"></div>
      <div class="float-chip tl"><span class="dot"></span> Freie Plätze in deiner Nähe</div>
      <div class="float-chip br">🧸 Liebevoll betreut</div>
    </div>
  </div>
</section>

<div class="stats">
  <div class="stats-grid">
    <div class="stat"><div class="n" id="stat-count"><?= $anzahl ?></div><div class="l">Tagesmütter gelistet</div></div>
    <div class="stat"><div class="n" id="stat-frei"><?= $freie ?></div><div class="l">freie Plätze</div></div>
    <div class="stat"><div class="n" id="stat-orte"><?= $orte ?></div><div class="l">Orte abgedeckt</div></div>
    <div class="stat"><div class="n">0&nbsp;€</div><div class="l">Kosten für Eltern</div></div>
  </div>
</div>

<section class="block" id="liste">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow">🔎 Übersicht</span>
      <h2>Tagesmütter in deiner Nähe</h2>
      <p>Wähle Bundesland und Stadt, filtere nach Alter und freien Plätzen – ein Klick auf ein Profil zeigt die ganze Vorstellung.</p>
    </div>
    <div class="filterbar reveal">
      <button class="filter-toggle" id="filter-toggle" type="button" aria-expanded="false" aria-controls="filters">⚙&nbsp; Filter &amp; Sortierung <span class="ft-badge" id="ft-badge" hidden>0</span></button>
      <div class="filters" id="filters">
        <select id="f-bundesland" data-bl-feld aria-label="Bundesland filtern"></select>
        <select id="f-alter" aria-label="Altersgruppe filtern">
          <option value="">Jedes Alter</option>
          <option>0–1 Jahr</option>
          <option>1–3 Jahre</option>
          <option>3+ Jahre</option>
        </select>
        <select id="f-extra" aria-label="Betreuungs-Extra filtern"><option value="">Alle Extras</option></select>
        <select id="f-sort" aria-label="Sortierung">
          <option value="plaetze">Sortierung: freie Plätze</option>
          <option value="name">Sortierung: Name (A–Z)</option>
        </select>
        <label class="toggle"><input type="checkbox" id="f-frei"> nur freie Plätze</label>
        <label class="toggle"><input type="checkbox" id="f-fav"> ❤️ nur gemerkte</label>
        <button class="filter-reset" id="filter-reset" type="button" hidden>✕ Zurücksetzen</button>
      </div>
      <div class="active-filters" id="active-filters"></div>
    </div>
    <p class="count-line" id="count"><?= $anzahl === 1 ? '1 Tagesmutter gefunden' : $anzahl . ' Tagesmütter gefunden' ?></p>
    <div class="grid" id="grid"><?php
      if ($anzahl > 0) { foreach ($eintraege as $x) echo idx_karte($x, $e); }
      else { echo '<div class="empty"><span class="emo">🧸</span>Noch keine Tagesmütter eingetragen.<span class="sub">Sei die Erste – kostenlos in 2 Minuten eingetragen.</span><a href="registrieren.php" class="btn btn-coral">Jetzt eintragen</a></div>'; }
    ?></div>
  </div>
</section>

<section class="block benefits">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow">💡 Gut zu wissen</span>
      <h2>Warum Kindertagespflege?</h2>
      <p>Die familiäre Alternative zur Kita – anerkannt, gefördert und oft flexibler.</p>
    </div>
    <div class="benefit-grid">
      <div class="benefit reveal">
        <div class="ico">🏡</div>
        <h3>Familiär &amp; geborgen</h3>
        <p>Betreuung in kleiner Gruppe (max. 5 Kinder) im Zuhause der Tagesmutter – mit Ruhe, Nähe und festen Ritualen.</p>
      </div>
      <div class="benefit reveal">
        <div class="ico">🕐</div>
        <h3>Flexible Zeiten</h3>
        <p>Viele Tagesmütter bieten Randzeiten, Teilzeitmodelle oder einspringen bei Schichtarbeit – individueller als jede Einrichtung.</p>
      </div>
      <div class="benefit reveal">
        <div class="ico">🛡️</div>
        <h3>Offiziell geprüft</h3>
        <p>Jede Tagesmutter braucht eine Pflegeerlaubnis des Jugendamts (§ 43 SGB VIII) – inkl. Qualifikation und regelmäßiger Prüfung.</p>
      </div>
    </div>
  </div>
</section>

<section class="block" id="so-gehts">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow">🚀 In 3 Schritten</span>
      <h2>So funktioniert’s</h2>
    </div>
    <div class="steps-grid">
      <div class="step reveal"><div class="num">1</div><h4>Suchen &amp; filtern</h4><p>Stadtteil wählen, Alter angeben – passende Tagesmütter mit freien Plätzen finden.</p></div>
      <div class="step reveal"><div class="num">2</div><h4>Direkt Kontakt aufnehmen</h4><p>Per E-Mail oder Telefon – ohne Umwege, ohne Anmeldung, ohne Kosten.</p></div>
      <div class="step reveal"><div class="num">3</div><h4>Kennenlernen</h4><p>Schnuppertermin vereinbaren und in Ruhe entscheiden, ob es passt.</p></div>
    </div>
  </div>
</section>

<section class="block" id="kosten">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow">💶 Kosten &amp; Förderung</span>
      <h2>Was kostet Kindertagespflege?</h2>
      <p>Kindertagespflege wird wie ein Kita-Platz öffentlich gefördert – für viele Familien bleibt nur ein einkommensabhängiger Eigenanteil.</p>
    </div>
    <div class="benefit-grid">
      <div class="benefit reveal">
        <div class="ico">🏛️</div>
        <h3>Öffentlich gefördert</h3>
        <p>Das Jugendamt bzw. Landratsamt übernimmt den Großteil der Betreuungskosten. Kindertagespflege ist der Kita rechtlich gleichgestellt (§ 24 SGB&nbsp;VIII).</p>
      </div>
      <div class="benefit reveal">
        <div class="ico">📊</div>
        <h3>Einkommensabhängiger Anteil</h3>
        <p>Eltern zahlen meist nur einen gestaffelten Eigenanteil – abhängig von Einkommen, Betreuungsumfang und Kommune. Vielerorts ist die Betreuung sogar beitragsfrei.</p>
      </div>
      <div class="benefit reveal">
        <div class="ico">📝</div>
        <h3>So läuft die Förderung</h3>
        <p>Passende Tagesmutter finden, Kontakt aufnehmen und den Antrag beim zuständigen Jugendamt stellen. Die Tagesmutter kennt den Ablauf und hilft dir dabei.</p>
      </div>
    </div>
  </div>
</section>

<section class="block register" id="eintragen">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow">👩‍🏫 Für Tagesmütter</span>
      <h2>Du betreust Kinder? Trag dich ein!</h2>
      <p>Kostenlos sichtbar für Eltern in deiner Stadt – in 2 Minuten ausgefüllt.</p>
    </div>
    <div class="register-grid">
      <div class="register-side reveal">
        <div class="frame"><img src="img/eintragen.jpg" alt="Illustration: Tagesmutter trägt sich am Laptop ein, daneben spielt ein Kind" width="900" height="1125" loading="lazy"></div>
        <div class="perk"><div class="tick">✓</div><div><b>Kostenlos gefunden werden</b><span>Eltern aus deinem Ort sehen dein Profil zuerst.</span></div></div>
        <div class="perk"><div class="tick">✓</div><div><b>Eigene Profilseite</b><span>Deine persönliche Vorstellung auf einer eigenen Seite – teilbar per Link.</span></div></div>
        <div class="perk"><div class="tick">✓</div><div><b>Volle Kontrolle</b><span>Dein Profil jederzeit selbst bearbeiten – nach dem Login in deinem Konto.</span></div></div>
      </div>
      <div class="reveal" style="align-self:center">
        <h3 style="font-size:1.5rem;font-weight:800;letter-spacing:-.01em;margin-bottom:.7rem">In wenigen Minuten eingetragen</h3>
        <p style="color:var(--ink-soft);margin-bottom:1.6rem">Leg dir ein kostenloses Konto an und fülle dein Profil aus. Nach einer kurzen Prüfung bist du für Eltern in deiner Stadt sichtbar – und kannst deine Angaben jederzeit selbst anpassen.</p>
        <a href="registrieren.php" class="btn btn-coral" style="font-size:1.05rem;padding:.9rem 2.1rem">Jetzt kostenlos registrieren</a>
        <p style="margin-top:1.1rem;font-size:.92rem;color:var(--muted)">Schon dabei? <a href="login.php" style="color:var(--coral);font-weight:800;text-decoration:none">Hier anmelden</a></p>
      </div>
    </div>
  </div>
</section>

<section class="block" id="faq">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow">❓ Häufige Fragen</span>
      <h2>FAQ</h2>
    </div>
    <div class="faq reveal">
      <details>
        <summary>Was kostet die Nutzung des Portals?</summary>
        <div class="a">Nichts – weder für Eltern noch für Tagesmütter. Das Portal finanziert sich über dezente Werbeanzeigen am Seitenrand.</div>
      </details>
      <details>
        <summary>Was ist Kindertagespflege eigentlich?</summary>
        <div class="a">Die Betreuung von Kindern (meist 0–3 Jahre) durch qualifizierte Tagesmütter oder Tagesväter – in kleinen Gruppen von maximal fünf Kindern, in der Regel im Zuhause der Betreuungsperson. Sie ist gesetzlich anerkannt und wird vom Landratsamt gefördert wie ein Kita-Platz.</div>
      </details>
      <details>
        <summary>Wie werden die Profile geprüft?</summary>
        <div class="a">Jede Tagesmutter trägt sich selbst ein und willigt in die Veröffentlichung ein. Vor der Freischaltung prüfen wir die Angaben – insbesondere die Pflegeerlaubnis nach § 43 SGB VIII, die das Jugendamt nach Qualifikationsnachweis und Eignungsprüfung erteilt.</div>
      </details>
      <details>
        <summary>Wie bekomme ich einen Betreuungsplatz?</summary>
        <div class="a">Tagesmutter mit freien Plätzen suchen, direkt Kontakt aufnehmen und einen Kennenlerntermin vereinbaren. Die Kostenübernahme bzw. Förderung läuft anschließend über das zuständige Jugendamt – die Tagesmutter kennt den Ablauf und hilft dabei.</div>
      </details>
    </div>
  </div>
</section>

<section class="block" id="staedte">
  <div class="wrap">
    <div class="sec-head reveal">
      <span class="eyebrow">📍 Nach Stadt</span>
      <h2>Kindertagespflege in deiner Stadt</h2>
      <p>Wähle deine Stadt und sieh alle Tagesmütter dort auf einen Blick.</p>
    </div>
    <div class="staedte-grid reveal" id="staedte-grid"><?php
      foreach (TMF_STAEDTE as $stadt) echo '<a href="/tagesmutter/' . tmf_slug($stadt) . '">' . $e($stadt) . '</a>';
    ?></div>
  </div>
</section>

<footer>
  <div class="footer-grid">
    <div>
      <a class="logo" href="/">
        <img src="img/logo-tagesmutter.png" alt="Tagesmutter finden" class="logo-img" style="height:50px">
      </a>
      <p class="brand-txt">Das Verzeichnis für Kindertagespflege in deiner Region. Eltern finden Betreuung, Tagesmütter werden gefunden – einfach, direkt und kostenlos.</p>
    </div>
    <div>
      <h5>Für Eltern</h5>
      <ul>
        <li><a href="#liste">Tagesmütter finden</a></li>
        <li><a href="#so-gehts">So funktioniert’s</a></li>
        <li><a href="#faq">Häufige Fragen</a></li>
      </ul>
    </div>
    <div>
      <h5>Für Tagesmütter</h5>
      <ul>
        <li><a href="#eintragen">Kostenlos eintragen</a></li>
        <li><a href="agb.html">Nutzungsbedingungen</a></li>
        <li><a href="impressum.html">Impressum</a></li>
        <li><a href="datenschutz.html">Datenschutz</a></li>
      </ul>
    </div>
  </div>
  <div class="powered">
    <span class="pw-label">Powered by</span>
    <a class="pw-gaseit" href="https://gaseit.de" target="_blank" rel="noopener" aria-label="Gaseit GmbH">
      <img src="img/gaseit-logo.png" alt="Gaseit GmbH" class="pw-gaseit-img">
    </a>
  </div>
  <div class="footer-bottom">
    <a href="impressum.html">Impressum</a> · <a href="datenschutz.html">Datenschutz</a> · <a href="agb.html">Nutzungsbedingungen</a> · <a href="admin.php">Admin-Bereich</a>
  </div>
</footer>

<div class="toast" id="toast">✅ Danke! Dein Eintrag wurde übermittelt und erscheint nach kurzer Prüfung.</div>

<script>window.__ALLE = <?= json_encode($eintraege, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;</script>
<script src="data.js"></script>
<script>
// ---------- Rendering ----------
const grid = document.getElementById("grid");
const countEl = document.getElementById("count");
let ALLE = [];   // vom Server geladene, freigegebene Einträge (SSR: window.__ALLE)

function render(){
  const q = document.getElementById("search").value.trim().toLowerCase();
  const fBl = document.getElementById("f-bundesland")?.value || ""; // im BW-Modus versteckt → immer leer
  const fOrt = document.getElementById("f-ort").value;
  const fAlter = document.getElementById("f-alter").value;
  const nurFrei = document.getElementById("f-frei").checked;
  const nurFav = document.getElementById("f-fav").checked;
  const fExtra = document.getElementById("f-extra").value;
  const fSort = document.getElementById("f-sort").value;
  const favs = favGet();

  let list = ALLE.filter(e =>
    (!q || e.name.toLowerCase().includes(q) || e.ort.toLowerCase().includes(q)) &&
    (!fBl || e.bundesland === fBl) &&
    (!fOrt || e.ort === fOrt) &&
    (!fAlter || e.alter.includes(fAlter)) &&
    (!fExtra || (e.extras && e.extras.includes(fExtra))) &&
    (!nurFrei || e.plaetze > 0) &&
    (!nurFav || favs.includes(e.id))
  );
  if(fSort === "name") list.sort((a,b) => a.name.localeCompare(b.name, "de"));
  else list.sort((a,b) => b.plaetze - a.plaetze);

  countEl.textContent = list.length === 1 ? "1 Tagesmutter gefunden" : `${list.length} Tagesmütter gefunden`;
  const statCount = document.getElementById("stat-count");
  if(statCount) statCount.textContent = ALLE.length;

  const karteHtml = e => {
    const teaser = e.persoenlich ? (e.persoenlich.length > 140 ? e.persoenlich.slice(0,140).trimEnd()+" …" : e.persoenlich) : "";
    return `
    <article class="card" data-id="${esc(e.id)}" tabindex="0" role="link" aria-label="Profilseite von ${esc(e.name)} öffnen">
      <button class="fav-btn${favHas(e.id)?' on':''}" data-fav="${esc(e.id)}" title="Merken" aria-label="Zur Merkliste">${favHas(e.id)?'❤️':'🤍'}</button>
      <div class="card-top">
        ${e.foto
          ? `<div class="avatar"><img src="${esc(e.foto)}" alt="Foto von ${esc(e.name)}" loading="lazy"></div>`
          : `<div class="avatar" style="background:${avColor(e.name)}">${esc(e.name.trim()[0] || "?")}</div>`}
        <div>
          <h3>${esc(e.name)}</h3>
          <div class="ort">📍 ${esc(e.ort)}${e.bundesland ? ` <span class="bl">· ${esc(e.bundesland)}</span>` : ""}</div>
        </div>
      </div>
      <div class="badges">
        ${badgePlaetze(e.plaetze)}
        ${e.erlaubnis ? '<span class="badge b-check" title="Pflegeerlaubnis vom Jugendamt nach § 43 SGB VIII">✓ Pflegeerlaubnis §43</span>' : ""}
        ${frischeBadge(e)}
      </div>
      ${teaser ? `<p class="desc">${esc(teaser)}</p>` : ""}
      <div class="meta">
        <span>🕐 <b>${esc(e.zeiten)}</b></span>
        ${e.frei_ab ? `<span>🗓️ frei ab <b>${esc(e.frei_ab)}</b></span>` : ""}
        <span class="chips">${e.alter.map(a => `<span class="chip">${esc(a)}</span>`).join("")}</span>
      </div>
      <div class="contact">
        <a class="btn btn-coral" href="${profilUrl(e.id)}">👤 Profil ansehen</a>
        <a class="btn btn-ghost" href="mailto:${esc(e.email)}">✉️ Kontakt</a>
      </div>
    </article>`;
  };

  if(list.length){
    grid.innerHTML = list.map(karteHtml).join("");
  } else if(ALLE.length && (fOrt || fBl || q)){
    // Punkt 3: keine Treffer, aber Suche aktiv → Tagesmütter in der Nähe anbieten (statt Sackgasse)
    const passtRest = e => (!fAlter || e.alter.includes(fAlter)) && (!fExtra || (e.extras && e.extras.includes(fExtra))) && (!nurFrei || e.plaetze > 0) && (!nurFav || favs.includes(e.id));
    let nachbarn = ALLE.filter(e => passtRest(e) && (!fBl || e.bundesland === fBl) && e.ort !== fOrt);
    if(!nachbarn.length && fBl) nachbarn = ALLE.filter(e => passtRest(e) && e.ort !== fOrt);
    nachbarn = nachbarn.sort((a,b) => b.plaetze - a.plaetze).slice(0, 6);
    const wo = fOrt || fBl || "deiner Suche";
    grid.innerHTML = nachbarn.length
      ? `<div class="empty near-hint"><span class="emo">🧭</span>In <b>${esc(wo)}</b> noch keine passende Tagesmutter – aber diese sind in der Nähe:</div>` + nachbarn.map(karteHtml).join("")
      : '<div class="empty"><span class="emo">🔍</span>Keine Tagesmütter für diese Auswahl.<span class="sub">Versuch weniger Filter.</span></div>';
  } else if(ALLE.length){
    grid.innerHTML = '<div class="empty"><span class="emo">🔍</span>Keine Tagesmütter für diese Auswahl.<span class="sub">Versuch weniger Filter oder eine andere Stadt.</span></div>';
  } else {
    grid.innerHTML = '<div class="empty"><span class="emo">🧸</span>Noch keine Tagesmütter eingetragen.<span class="sub">Sei die Erste – kostenlos in 2 Minuten eingetragen.</span><a href="registrieren.php" class="btn btn-coral">Jetzt eintragen</a></div>';
  }

  // Aktive-Filter-Chips (jeder mit ✕ zum Entfernen) + Reset-Sichtbarkeit
  const aktiv = [];
  if(q)       aktiv.push(["🔎 " + q,          () => document.getElementById("search").value = ""]);
  if(fBl)     aktiv.push(["📍 " + fBl,         () => { const b = document.getElementById("f-bundesland"); b.value = ""; b.dispatchEvent(new Event("change")); }]);
  if(fOrt)    aktiv.push(["📍 " + fOrt,        () => document.getElementById("f-ort").value = ""]);
  if(fAlter)  aktiv.push([fAlter,             () => document.getElementById("f-alter").value = ""]);
  if(fExtra)  aktiv.push([fExtra,             () => document.getElementById("f-extra").value = ""]);
  if(nurFrei) aktiv.push(["nur freie Plätze", () => document.getElementById("f-frei").checked = false]);
  if(nurFav)  aktiv.push(["❤️ nur gemerkte",  () => document.getElementById("f-fav").checked = false]);
  const af = document.getElementById("active-filters");
  af.innerHTML = aktiv.map((a,i) => `<span class="af-chip">${esc(a[0])} <button type="button" data-ai="${i}" aria-label="Filter „${esc(a[0])}“ entfernen">✕</button></span>`).join("");
  af.querySelectorAll("button").forEach(b => b.addEventListener("click", () => { aktiv[+b.dataset.ai][1](); render(); }));
  document.getElementById("filter-reset").hidden = aktiv.length === 0;
  const badge = document.getElementById("ft-badge");
  if(badge){ badge.hidden = aktiv.length === 0; badge.textContent = aktiv.length; }

  // Punkt 1: Filterzustand in die URL spiegeln → teilbar + bleibt bei „Zurück"
  const up = new URLSearchParams();
  if(q) up.set("q", q);
  if(fBl) up.set("bl", fBl);
  if(fOrt) up.set("ort", fOrt);
  if(fAlter) up.set("alter", fAlter);
  if(fExtra) up.set("extra", fExtra);
  if(nurFrei) up.set("frei", "1");
  if(nurFav) up.set("fav", "1");
  if(fSort !== "plaetze") up.set("sort", fSort);
  const qs = up.toString();
  history.replaceState(null, "", location.pathname + (qs ? "?" + qs : "") + location.hash);
}

// Einträge anzeigen: SSR-Daten (window.__ALLE) direkt nutzen; sonst vom Server holen
async function ladeUndRender(){
  if(Array.isArray(window.__ALLE)){ ALLE = window.__ALLE; render(); return; }
  grid.innerHTML = Array.from({length:6}, () => `
    <div class="skeleton-card" aria-hidden="true">
      <div class="sk-row"><div class="sk sk-avatar"></div><div style="flex:1"><div class="sk sk-line" style="width:62%"></div><div class="sk sk-line" style="width:40%;margin:0"></div></div></div>
      <div class="sk sk-line" style="width:92%"></div>
      <div class="sk sk-line" style="width:78%"></div>
      <div class="sk sk-line" style="width:55%;margin-bottom:1.1rem"></div>
      <div class="sk sk-line" style="height:40px;width:100%"></div>
    </div>`).join("");
  try{ ALLE = await ladeEintraege(); }
  catch(err){ ALLE = []; }
  render();
}

// Klick auf die Karte öffnet die Profilseite (echte Links bleiben Links)
grid.addEventListener("click", ev => {
  const favBtn = ev.target.closest(".fav-btn");
  if(favBtn){
    ev.stopPropagation();
    const on = favToggle(favBtn.dataset.fav);
    favBtn.classList.toggle("on", on);
    favBtn.textContent = on ? "❤️" : "🤍";
    if(document.getElementById("f-fav").checked) render();
    return;
  }
  if(ev.target.closest("a")) return;
  const card = ev.target.closest(".card");
  if(card) location.href = profilUrl(card.dataset.id);
});
grid.addEventListener("keydown", ev => {
  if(ev.key === "Enter" && ev.target.classList.contains("card"))
    location.href = profilUrl(ev.target.dataset.id);
});

// ---------- Filter-Setup: Bundesland → Stadt ----------
initStadtauswahl(document.getElementById("f-bundesland"), document.getElementById("f-ort"), "", "", true);
document.getElementById("f-bundesland").addEventListener("change", render); // greift nur im Deutschland-Modus (sonst versteckt)
document.getElementById("f-extra").innerHTML = '<option value="">Alle Extras</option>' + EXTRAS.map(x => `<option>${x}</option>`).join("");
["search","f-ort","f-alter","f-extra","f-frei","f-sort","f-fav"].forEach(id => {
  const el = document.getElementById(id);
  ["input","change"].forEach(evt => el.addEventListener(evt, render)); // input = live-tippen, change = Dropdowns/Checkboxen zuverlässig
});

// Alle Filter zurücksetzen
document.getElementById("filter-reset").addEventListener("click", () => {
  document.getElementById("search").value = "";
  const bl = document.getElementById("f-bundesland"); if(bl){ bl.value = ""; bl.dispatchEvent(new Event("change")); }
  document.getElementById("f-ort").value = "";
  document.getElementById("f-alter").value = "";
  document.getElementById("f-extra").value = "";
  document.getElementById("f-sort").value = "plaetze";
  document.getElementById("f-frei").checked = false;
  document.getElementById("f-fav").checked = false;
  render();
});
// Filter-Panel ein-/ausklappen (mobil)
document.getElementById("filter-toggle").addEventListener("click", () => {
  const f = document.getElementById("filters");
  document.getElementById("filter-toggle").setAttribute("aria-expanded", f.classList.toggle("open"));
});

// „Finden" im Hero → dedizierte Stadt-Landingpage (SEO) oder zur Liste scrollen
document.getElementById("stadt-go").addEventListener("click", () => {
  const ort = document.getElementById("f-ort").value;
  if(ort) location.href = stadtUrl(ort);
  else document.getElementById("liste").scrollIntoView({behavior:"smooth"});
});
// Städte-Übersicht ist bereits server-seitig gerendert (interne Links → Stadt-Landingpages)

// Punkt 1: gespeicherte Filter aus der URL wiederherstellen (vor dem ersten Laden)
(function(){
  const p = new URLSearchParams(location.search);
  if(![...p.keys()].length) return;
  if(p.has("q")) document.getElementById("search").value = p.get("q");
  if(p.has("bl")){ const b = document.getElementById("f-bundesland"); if(b){ b.value = p.get("bl"); b.dispatchEvent(new Event("change")); } }
  if(p.has("ort")) document.getElementById("f-ort").value = p.get("ort");
  if(p.has("alter")) document.getElementById("f-alter").value = p.get("alter");
  if(p.has("extra")) document.getElementById("f-extra").value = p.get("extra");
  if(p.has("frei")) document.getElementById("f-frei").checked = true;
  if(p.has("fav")) document.getElementById("f-fav").checked = true;
  if(p.has("sort")) document.getElementById("f-sort").value = p.get("sort");
})();

// ---------- Live-Statistiken (aktualisiert die server-seitigen Startwerte) ----------
fetch("api/stats.php").then(r => r.json()).then(s => {
  const set = (id,v) => { const el = document.getElementById(id); if(el) el.textContent = v; };
  set("stat-count", s.anzahl); set("stat-frei", s.freie); set("stat-orte", s.orte);
}).catch(() => {});

// ---------- Header-Schatten ----------
const headerEl = document.getElementById("header");
addEventListener("scroll", () => headerEl.classList.toggle("scrolled", scrollY > 8), {passive:true});

// ---------- Scroll-Reveal ----------
const io = new IntersectionObserver(entries => {
  entries.forEach(en => { if(en.isIntersecting){ en.target.classList.add("in"); io.unobserve(en.target); } });
}, {threshold:.12});
document.querySelectorAll(".reveal").forEach(el => io.observe(el));

ladeUndRender();
</script>
</body>
</html>

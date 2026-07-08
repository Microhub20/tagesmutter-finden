<?php
/**
 * Dynamische XML-Sitemap: statische Seiten + Stadt-Landingpages + alle freigegebenen Profile.
 * Mit lastmod (Dateidatum bzw. updated_at), changefreq und priority.
 * Erreichbar als /sitemap.php UND /sitemap.xml (Route im Front-Controller in index.php),
 * referenziert in robots.txt.
 */
declare(strict_types=1);
require __DIR__ . '/api/db.php';

header('Content-Type: application/xml; charset=utf-8');
$base = 'https://mein-tageskind.de';

/** Ein <url>-Element bauen. */
function tmf_url(string $base, string $path, ?string $lastmod, string $freq, string $prio): string {
    $u = "  <url><loc>{$base}{$path}</loc>";
    if ($lastmod) $u .= "<lastmod>{$lastmod}</lastmod>";
    return $u . "<changefreq>{$freq}</changefreq><priority>{$prio}</priority></url>\n";
}
/** Änderungsdatum einer lokalen Datei (Y-m-d) oder null. */
function tmf_mtime(string $rel): ?string {
    $f = __DIR__ . $rel;
    return is_file($f) ? date('Y-m-d', (int) filemtime($f)) : null;
}

$out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Startseite (wichtigste Seite, Statistiken ändern sich)
$out .= tmf_url($base, '/', tmf_mtime('/index.php'), 'daily', '1.0');
// Ratgeber-Hub + Über-uns
$out .= tmf_url($base, '/ratgeber.html', tmf_mtime('/ratgeber.html'), 'weekly', '0.7');
$out .= tmf_url($base, '/ueber-uns.html', tmf_mtime('/ueber-uns.html'), 'monthly', '0.5');

// Ratgeber-Artikel
$artikel = ['ratgeber-kosten','ratgeber-foerderung-antrag','ratgeber-eingewoehnung',
    'ratgeber-kita-oder-tagespflege','ratgeber-rechtsanspruch-betreuungsplatz',
    'ratgeber-pflegeerlaubnis-paragraf-43','ratgeber-gute-tagesmutter','ratgeber-betreuungsvertrag',
    'ratgeber-ab-wann-fremdbetreuung','ratgeber-krank-vertretung','ratgeber-steuer-absetzen',
    'ratgeber-uebergang-kindergarten','ratgeber-tagesmutter-werden','ratgeber-selbststaendig-tagespflege'];
foreach ($artikel as $slug) {
    $out .= tmf_url($base, "/{$slug}.html", tmf_mtime("/{$slug}.html"), 'monthly', '0.6');
}
// Rechtstexte (selten geändert)
foreach (['agb','impressum','datenschutz'] as $slug) {
    $out .= tmf_url($base, "/{$slug}.html", tmf_mtime("/{$slug}.html"), 'yearly', '0.3');
}

try {
    $pdo = tmf_db();
    // Stadt-Landingpages – nur Städte MIT mindestens einer freigegebenen Tagesmutter
    // (leere Städte sind noindex und gehören nicht in die Sitemap – kein Thin-Content-Signal)
    $mitEintrag = [];
    foreach ($pdo->query("SELECT DISTINCT ort FROM tagesmuetter WHERE status = 'approved'") as $r) {
        $mitEintrag[(string)$r['ort']] = true;
    }
    foreach (TMF_STAEDTE as $stadt) {
        if (isset($mitEintrag[$stadt])) {
            $out .= tmf_url($base, '/tagesmutter/' . tmf_slug($stadt), null, 'weekly', '0.8');
        }
    }
    // Einzelne Profile (lastmod aus updated_at/created_at)
    foreach ($pdo->query("SELECT id, updated_at, created_at FROM tagesmuetter WHERE status = 'approved'") as $r) {
        $lm = substr((string)(($r['updated_at'] ?? '') ?: ($r['created_at'] ?? '')), 0, 10) ?: null;
        $out .= tmf_url($base, '/profil/' . rawurlencode((string)$r['id']), $lm, 'weekly', '0.7');
    }
} catch (Throwable $e) { /* Sitemap trotzdem mit statischen URLs ausliefern */ }

$out .= '</urlset>';
echo $out;

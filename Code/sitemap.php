<?php
/**
 * Dynamische XML-Sitemap: statische Seiten + alle freigegebenen Profile.
 * Erreichbar als /sitemap.php (in robots.txt referenziert).
 */
declare(strict_types=1);
require __DIR__ . '/api/db.php';

header('Content-Type: application/xml; charset=utf-8');
$base = 'https://mein-tageskind.de';

$out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (['/', '/ueber-uns.html', '/ratgeber.html', '/ratgeber-kosten.html', '/ratgeber-foerderung-antrag.html', '/ratgeber-eingewoehnung.html', '/ratgeber-kita-oder-tagespflege.html', '/ratgeber-tagesmutter-werden.html', '/ratgeber-rechtsanspruch-betreuungsplatz.html', '/ratgeber-pflegeerlaubnis-paragraf-43.html', '/ratgeber-gute-tagesmutter.html', '/agb.html', '/impressum.html', '/datenschutz.html'] as $u) {
    $out .= "  <url><loc>{$base}{$u}</loc></url>\n";
}
try {
    $pdo = tmf_db();
    // Stadt-Landingpages – alle Städte der Region (auch ohne Einträge: dank lokalem
    // Info-Text + Tagesmutter-Einladung inhaltlich substanziell und indexierbar)
    foreach (TMF_STAEDTE as $stadt) {
        $out .= "  <url><loc>{$base}/tagesmutter/" . tmf_slug($stadt) . "</loc></url>\n";
    }
    // Einzelne Profile
    foreach ($pdo->query("SELECT id FROM tagesmuetter WHERE status = 'approved'") as $r) {
        $out .= "  <url><loc>{$base}/profil/" . rawurlencode((string)$r['id']) . "</loc></url>\n";
    }
} catch (Throwable $e) { /* Sitemap trotzdem mit statischen URLs ausliefern */ }
$out .= '</urlset>';
echo $out;

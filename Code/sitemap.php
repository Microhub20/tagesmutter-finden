<?php
/**
 * Dynamische XML-Sitemap: statische Seiten + alle freigegebenen Profile.
 * Erreichbar als /sitemap.php (in robots.txt referenziert).
 */
declare(strict_types=1);
require __DIR__ . '/api/db.php';

header('Content-Type: application/xml; charset=utf-8');
$base = 'https://tagesmutter-vergleich.de';

$out  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (['/', '/impressum.html', '/datenschutz.html'] as $u) {
    $out .= "  <url><loc>{$base}{$u}</loc></url>\n";
}
try {
    $pdo = tmf_db();
    // Stadt-Landingpages – nur Städte mit freigegebenen Angeboten (die auf "index" stehen)
    foreach ($pdo->query("SELECT DISTINCT ort FROM tagesmuetter WHERE status = 'approved' AND ort <> ''") as $r) {
        $out .= "  <url><loc>{$base}/tagesmutter/" . tmf_slug((string)$r['ort']) . "</loc></url>\n";
    }
    // Einzelne Profile
    foreach ($pdo->query("SELECT id FROM tagesmuetter WHERE status = 'approved'") as $r) {
        $out .= "  <url><loc>{$base}/profil.html?id=" . rawurlencode((string)$r['id']) . "</loc></url>\n";
    }
} catch (Throwable $e) { /* Sitemap trotzdem mit statischen URLs ausliefern */ }
$out .= '</urlset>';
echo $out;

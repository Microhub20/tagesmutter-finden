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
    foreach (tmf_db()->query("SELECT id FROM tagesmuetter WHERE status = 'approved'") as $r) {
        $out .= "  <url><loc>{$base}/profil.html?id=" . rawurlencode((string)$r['id']) . "</loc></url>\n";
    }
} catch (Throwable $e) { /* Sitemap trotzdem mit statischen URLs ausliefern */ }
$out .= '</urlset>';
echo $out;

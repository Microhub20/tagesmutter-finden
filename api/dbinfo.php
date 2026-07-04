<?php
/**
 * TEMPORÄR (Diagnose): zeigt, welcher DB-Treiber live wirklich genutzt wird –
 * ohne Zugangsdaten preiszugeben. Nach der Prüfung wieder entfernen.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
try {
    $pdo = tmf_db();
    $cfg = tmf_config();
    $anzahl = (int)$pdo->query("SELECT COUNT(*) FROM tagesmuetter")->fetchColumn();
    tmf_json([
        'treiber'         => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),   // 'mysql' oder 'sqlite'
        'server_version'  => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'config_php_da'   => is_file(__DIR__ . '/config.php'),
        'db_host_gesetzt' => !empty($cfg['DB_HOST']),
        'eintraege'       => $anzahl,
    ]);
} catch (Throwable $e) {
    tmf_json(['error' => 'db_fehler', 'msg' => $e->getMessage()], 500);
}

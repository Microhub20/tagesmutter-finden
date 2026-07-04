<?php
/**
 * Liefert Live-Kennzahlen für die Startseite (nur freigegebene Einträge).
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

try {
    $pdo = tmf_db();
    $row = $pdo->query(
        "SELECT
            COUNT(*)                                            AS anzahl,
            COALESCE(SUM(CASE WHEN plaetze > 0 THEN plaetze ELSE 0 END), 0) AS freie,
            COUNT(DISTINCT ort)                                 AS orte,
            COALESCE(SUM(erlaubnis), 0)                         AS geprueft
         FROM tagesmuetter WHERE status = 'approved'"
    )->fetch();
    tmf_json([
        'anzahl'   => (int)($row['anzahl']   ?? 0),
        'freie'    => (int)($row['freie']    ?? 0),
        'orte'     => (int)($row['orte']     ?? 0),
        'geprueft' => (int)($row['geprueft'] ?? 0),
    ]);
} catch (Throwable $e) {
    tmf_json(['anzahl' => 0, 'freie' => 0, 'orte' => 0, 'geprueft' => 0]);
}

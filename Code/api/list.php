<?php
/**
 * Liefert freigegebene Tagesmütter als JSON (inkl. Bewertungs-Schnitt).
 *  - GET api/list.php          → alle freigegebenen Einträge
 *  - GET api/list.php?id=<id>  → ein Profil + einzelne Bewertungen
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

try {
    $pdo = tmf_db();

    // Bewertungs-Durchschnitte je Tagesmutter (nur freigegebene Bewertungen)
    $avg = [];
    foreach ($pdo->query("SELECT tm_id, AVG(sterne) AS s, COUNT(*) AS c FROM bewertungen WHERE status='approved' GROUP BY tm_id") as $b) {
        $avg[$b['tm_id']] = ['avg' => round((float)$b['s'], 1), 'count' => (int)$b['c']];
    }

    $id = $_GET['id'] ?? null;
    if ($id !== null && $id !== '') {
        $stmt = $pdo->prepare("SELECT * FROM tagesmuetter WHERE id = ? AND status = 'approved'");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) tmf_json(['error' => 'not_found'], 404);
        $entry = tmf_row_to_entry($row);
        $entry['bewertung'] = $avg[$row['id']] ?? ['avg' => 0, 'count' => 0];
        $bs = $pdo->prepare("SELECT name, sterne, text, created_at FROM bewertungen WHERE tm_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 30");
        $bs->execute([$id]);
        $entry['bewertungen'] = $bs->fetchAll();
        tmf_json($entry);
    }

    $rows = $pdo->query(
        "SELECT * FROM tagesmuetter WHERE status = 'approved'
         ORDER BY (plaetze > 0) DESC, plaetze DESC, created_at DESC"
    )->fetchAll();

    tmf_json(array_map(function ($r) use ($avg) {
        $e = tmf_row_to_entry($r);
        $e['bewertung'] = $avg[$r['id']] ?? ['avg' => 0, 'count' => 0];
        return $e;
    }, $rows));
} catch (Throwable $e) {
    tmf_json(['error' => 'server_error'], 500);
}

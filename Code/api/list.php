<?php
/**
 * Liefert freigegebene Tagesmütter als JSON.
 *  - GET api/list.php          → alle freigegebenen Einträge
 *  - GET api/list.php?id=<id>  → ein einzelnes Profil
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

try {
    $pdo = tmf_db();

    $id = $_GET['id'] ?? null;
    if ($id !== null && $id !== '') {
        $stmt = $pdo->prepare("SELECT * FROM tagesmuetter WHERE id = ? AND status = 'approved'");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) tmf_json(['error' => 'not_found'], 404);
        tmf_json(tmf_row_to_entry($row));
    }

    $rows = $pdo->query(
        "SELECT * FROM tagesmuetter WHERE status = 'approved'
         ORDER BY (plaetze > 0) DESC, plaetze DESC, created_at DESC"
    )->fetchAll();

    tmf_json(array_map('tmf_row_to_entry', $rows));
} catch (Throwable $e) {
    tmf_json(['error' => 'server_error'], 500);
}

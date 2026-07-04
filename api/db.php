<?php
/**
 * Tagesmutter finden – Datenbank-Layer
 *
 * Nutzt auf Dogado MySQL (Zugänge aus config.php, die beim Deploy aus den
 * GitHub-Secrets generiert wird). Fehlt config.php (z. B. lokal), fällt der
 * Layer automatisch auf eine SQLite-Datei zurück – so lässt sich alles ohne
 * MySQL-Server testen.
 */

declare(strict_types=1);

function tmf_config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $file = __DIR__ . '/config.php';
    if (is_file($file)) {
        $cfg = require $file;                       // Dogado (MySQL)
    } else {
        $cfg = ['DB_DRIVER' => 'sqlite', 'ADMIN_PASS' => 'admin']; // lokaler Test
    }
    return $cfg;
}

function tmf_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $c = tmf_config();

    if (($c['DB_DRIVER'] ?? '') === 'sqlite' || empty($c['DB_HOST'])) {
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $pdo = new PDO('sqlite:' . $dir . '/tagesmutter.sqlite');
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $c['DB_HOST'], $c['DB_PORT'] ?? '3306', $c['DB_NAME']
        );
        $pdo = new PDO($dsn, $c['DB_USER'], $c['DB_PASS']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    tmf_init_schema($pdo);
    return $pdo;
}

function tmf_init_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tagesmuetter (
        id            VARCHAR(64)  PRIMARY KEY,
        name          VARCHAR(80)  NOT NULL,
        ort           VARCHAR(80)  NOT NULL,
        plaetze       INT          NOT NULL DEFAULT 0,
        zeiten        VARCHAR(120) NOT NULL,
        altersgruppen TEXT         NOT NULL,
        persoenlich   TEXT         NOT NULL,
        email         VARCHAR(120) NOT NULL,
        tel           VARCHAR(40),
        erlaubnis     INT          NOT NULL DEFAULT 0,
        foto          VARCHAR(200),
        status        VARCHAR(20)  NOT NULL DEFAULT 'pending',
        created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
    )");
}

/** Einen DB-Zeilensatz ins fürs Frontend erwartete Format bringen. */
function tmf_row_to_entry(array $r): array {
    return [
        'id'          => $r['id'],
        'name'        => $r['name'],
        'ort'         => $r['ort'],
        'plaetze'     => (int)$r['plaetze'],
        'zeiten'      => $r['zeiten'],
        'alter'       => json_decode($r['altersgruppen'] ?: '[]', true) ?: [],
        'persoenlich' => $r['persoenlich'],
        'email'       => $r['email'],
        'tel'         => $r['tel'] ?? '',
        'erlaubnis'   => (bool)$r['erlaubnis'],
        'foto'        => $r['foto'] ? 'uploads/' . $r['foto'] : '',
        'status'      => $r['status'],
    ];
}

/** JSON-Antwort senden und beenden. */
function tmf_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

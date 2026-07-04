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
        fotos         TEXT,
        bundesland    VARCHAR(60),
        qualifikation VARCHAR(120),
        sprachen      VARCHAR(120),
        frei_ab       VARCHAR(40),
        ernaehrung    VARCHAR(120),
        nichtraucher  INT          NOT NULL DEFAULT 0,
        haustiere     VARCHAR(80),
        konzept       TEXT,
        status        VARCHAR(20)  NOT NULL DEFAULT 'pending',
        passwort_hash VARCHAR(255),
        nummer        INT,
        created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME
    )");
    // Bestehende Tabellen sanft nachrüsten (Spalten wurden nachträglich eingeführt)
    tmf_ensure_column($pdo, 'tagesmuetter', 'passwort_hash', 'VARCHAR(255)');
    tmf_ensure_column($pdo, 'tagesmuetter', 'updated_at', 'DATETIME');
    tmf_ensure_column($pdo, 'tagesmuetter', 'nummer', 'INT');
    tmf_ensure_column($pdo, 'tagesmuetter', 'fotos', 'TEXT');        // JSON: bis zu 5 Galerie-Bilder
    tmf_ensure_column($pdo, 'tagesmuetter', 'bundesland', 'VARCHAR(60)');
    // v2.2: detailliertere Profile
    tmf_ensure_column($pdo, 'tagesmuetter', 'qualifikation', 'VARCHAR(120)');
    tmf_ensure_column($pdo, 'tagesmuetter', 'sprachen', 'VARCHAR(120)');
    tmf_ensure_column($pdo, 'tagesmuetter', 'frei_ab', 'VARCHAR(40)');
    tmf_ensure_column($pdo, 'tagesmuetter', 'ernaehrung', 'VARCHAR(120)');
    tmf_ensure_column($pdo, 'tagesmuetter', 'nichtraucher', 'INT');
    tmf_ensure_column($pdo, 'tagesmuetter', 'haustiere', 'VARCHAR(80)');
    tmf_ensure_column($pdo, 'tagesmuetter', 'konzept', 'TEXT');
    // v2.3
    tmf_ensure_column($pdo, 'tagesmuetter', 'extras', 'TEXT');            // JSON: Betreuungs-Extras
    tmf_ensure_column($pdo, 'tagesmuetter', 'reset_token', 'VARCHAR(64)');
    tmf_ensure_column($pdo, 'tagesmuetter', 'reset_expires', 'INT');

    // Kontaktanfragen von Eltern an Tagesmütter (Historie fürs Konto)
    $pdo->exec("CREATE TABLE IF NOT EXISTS anfragen (
        id         VARCHAR(32)  PRIMARY KEY,
        tm_id      VARCHAR(64)  NOT NULL,
        name       VARCHAR(80),
        email      VARCHAR(120),
        tel        VARCHAR(40),
        nachricht  TEXT,
        gelesen    INT          NOT NULL DEFAULT 0,
        created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
    )");

    // Platz-frei-Vormerkungen von Eltern
    $pdo->exec("CREATE TABLE IF NOT EXISTS vormerkungen (
        id             VARCHAR(32)  PRIMARY KEY,
        tm_id          VARCHAR(64)  NOT NULL,
        name           VARCHAR(80),
        email          VARCHAR(120),
        benachrichtigt INT          NOT NULL DEFAULT 0,
        created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP
    )");
}

/**
 * Nächste Mitgliedsnummer: 2-stelliges Registrierungsjahr + 4-stellig fortlaufend
 * (pro Jahr neu ab 0001). Beispiel 2026 → 260001, 260002 … 2027 → 270001.
 */
function tmf_next_nummer(PDO $pdo): int {
    $prefix = ((int)date('y')) * 10000;                 // z. B. 260000 für 2026
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(nummer), 0) FROM tagesmuetter WHERE nummer >= ? AND nummer < ?");
    $stmt->execute([$prefix, $prefix + 10000]);
    return max((int)$stmt->fetchColumn(), $prefix) + 1; // erste im Jahr = JJ0001
}

/** Mitgliedsnummer 6-stellig formatiert (z. B. 000042); '—' wenn keine. */
function tmf_usernr($n): string {
    return $n ? str_pad((string)(int)$n, 6, '0', STR_PAD_LEFT) : '—';
}

/** Spalte nur anlegen, wenn sie noch fehlt (funktioniert für MySQL und SQLite). */
function tmf_ensure_column(PDO $pdo, string $table, string $col, string $def): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $exists = false;
    if ($driver === 'sqlite') {
        foreach ($pdo->query("PRAGMA table_info(" . $table . ")") as $c) {
            if ($c['name'] === $col) { $exists = true; break; }
        }
    } else {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $col]);
        $exists = (int)$stmt->fetchColumn() > 0;
    }
    if (!$exists) $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$def}");
}

/**
 * Ein vom Client bereits verkleinertes Bild speichern. Gibt den neuen Dateinamen
 * zurück (oder null bei Fehler/ungültig). $dir = Zielordner (…/uploads).
 */
function tmf_save_image(string $tmp, int $size, string $dir): ?string {
    if ($tmp === '' || !is_uploaded_file($tmp) || $size > 4 * 1024 * 1024) return null;
    $info = @getimagesize($tmp);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
    if (!$ext) return null;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    return move_uploaded_file($tmp, $dir . '/' . $name) ? $name : null;
}

/** Galerie-Dateinamen sicher aus der DB-Spalte lesen (JSON-Array). */
function tmf_fotos_list($val): array {
    $arr = json_decode(($val ?? '') ?: '[]', true);
    return is_array($arr) ? array_values(array_filter($arr, 'is_string')) : [];
}

/** Einen DB-Zeilensatz ins fürs Frontend erwartete Format bringen. */
function tmf_row_to_entry(array $r): array {
    return [
        'id'          => $r['id'],
        'name'        => $r['name'],
        'ort'         => $r['ort'],
        'bundesland'  => $r['bundesland'] ?? '',
        'plaetze'     => (int)$r['plaetze'],
        'zeiten'      => $r['zeiten'],
        'alter'       => json_decode($r['altersgruppen'] ?: '[]', true) ?: [],
        'persoenlich' => $r['persoenlich'],
        'email'       => $r['email'],
        'tel'         => $r['tel'] ?? '',
        'erlaubnis'   => (bool)$r['erlaubnis'],
        'foto'        => $r['foto'] ? 'uploads/' . $r['foto'] : '',
        'fotos'       => array_map(fn($f) => 'uploads/' . $f, tmf_fotos_list($r['fotos'] ?? '')),
        'qualifikation' => $r['qualifikation'] ?? '',
        'sprachen'    => $r['sprachen'] ?? '',
        'frei_ab'     => $r['frei_ab'] ?? '',
        'ernaehrung'  => $r['ernaehrung'] ?? '',
        'nichtraucher'=> (bool)($r['nichtraucher'] ?? 0),
        'haustiere'   => $r['haustiere'] ?? '',
        'konzept'     => $r['konzept'] ?? '',
        'extras'      => json_decode(($r['extras'] ?? '') ?: '[]', true) ?: [],
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

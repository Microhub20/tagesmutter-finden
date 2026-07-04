<?php
/**
 * Authentifizierung für Tagesmütter (Selbstverwaltung des eigenen Profils).
 * Login per E-Mail + Passwort (bcrypt/PASSWORD_DEFAULT). Session-basiert.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function tmf_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

/** Tagesmutter per E-Mail finden (E-Mail ist der Login-Name). */
function tmf_find_by_email(string $email): ?array {
    $stmt = tmf_db()->prepare("SELECT * FROM tagesmuetter WHERE LOWER(email) = ?");
    $stmt->execute([mb_strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

function tmf_hash_pw(string $pass): string {
    return password_hash($pass, PASSWORD_DEFAULT);
}

/** Login prüfen; bei Erfolg Session setzen und Datensatz zurückgeben, sonst null. */
function tmf_login(string $email, string $pass): ?array {
    $row = tmf_find_by_email($email);
    if (!$row || empty($row['passwort_hash'])) return null;
    if (!password_verify($pass, $row['passwort_hash'])) return null;
    tmf_session();
    session_regenerate_id(true);
    $_SESSION['tmf_uid'] = $row['id'];
    return $row;
}

/** Aktuell eingeloggte Tagesmutter (oder null). */
function tmf_current_user(): ?array {
    tmf_session();
    if (empty($_SESSION['tmf_uid'])) return null;
    $stmt = tmf_db()->prepare("SELECT * FROM tagesmuetter WHERE id = ?");
    $stmt->execute([$_SESSION['tmf_uid']]);
    return $stmt->fetch() ?: null;
}

function tmf_logout(): void {
    tmf_session();
    unset($_SESSION['tmf_uid']);
}

/** Seite nur für eingeloggte Tagesmütter zugänglich machen. */
function tmf_require_login(): array {
    $u = tmf_current_user();
    if (!$u) { header('Location: login.php'); exit; }
    return $u;
}

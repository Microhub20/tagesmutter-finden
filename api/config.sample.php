<?php
/**
 * VORLAGE – nicht die echte Konfiguration.
 *
 * Die echte api/config.php wird beim Deploy automatisch aus den GitHub-Secrets
 * erzeugt (siehe .github/workflows/deploy.yml) und ist per .gitignore vom Repo
 * ausgeschlossen – die Passwörter landen also nie im öffentlichen Code.
 *
 * Lokal braucht es diese Datei nicht: fehlt api/config.php, nutzt db.php
 * automatisch eine SQLite-Datei und das Admin-Passwort "admin".
 */

return [
    'DB_HOST'    => '127.0.0.1',
    'DB_PORT'    => '3307',
    'DB_NAME'    => 'h773706_tagesmutter',
    'DB_USER'    => 'h773706_tagesmutter',
    'DB_PASS'    => 'WIRD_BEIM_DEPLOY_EINGESETZT',
    'ADMIN_PASS' => 'WIRD_BEIM_DEPLOY_EINGESETZT',
];

<?php
declare(strict_types=1);
require __DIR__ . '/api/auth.php';

if (isset($_GET['logout'])) { tmf_logout(); header('Location: /'); exit; }
if (tmf_current_user()) { header('Location: mein-konto.php'); exit; }

$fehler = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (tmf_login((string)($_POST['email'] ?? ''), (string)($_POST['passwort'] ?? ''))) {
        header('Location: mein-konto.php');
        exit;
    }
    $fehler = 'E-Mail oder Passwort ist nicht korrekt.';
}
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Anmelden – Tagesmutter finden</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="brand"><a href="/"><img src="img/logo-tagesmutter.png" alt="Tagesmutter finden" style="height:44px"></a></div>
    <h1>Anmelden</h1>
    <p class="sub">Zum Bearbeiten deines Tagesmutter-Profils</p>
    <?php if ($fehler): ?><div class="auth-err"><?= $e($fehler) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <div class="field">
        <label for="email">E-Mail</label>
        <input type="email" id="email" name="email" required autofocus autocomplete="email">
      </div>
      <div class="field">
        <label for="pw">Passwort</label>
        <input type="password" id="pw" name="passwort" required autocomplete="current-password">
      </div>
      <button class="btn btn-coral" type="submit">Anmelden</button>
    </form>
    <div class="links">
      <a href="passwort-vergessen.php">Passwort vergessen?</a><br>
      Noch kein Profil? <a href="registrieren.php">Jetzt kostenlos eintragen</a><br>
      <a href="/">← Zurück zur Startseite</a>
    </div>
  </div>
</div>
</body>
</html>

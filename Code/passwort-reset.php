<?php
declare(strict_types=1);
require __DIR__ . '/api/auth.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$pdo   = tmf_db();
$stmt  = $pdo->prepare("SELECT id FROM tagesmuetter WHERE reset_token = ? AND reset_expires > ?");
$stmt->execute([$token, time()]);
$uid = $stmt->fetchColumn();

$fehler = ''; $ok = false;
if ($uid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pass = (string)($_POST['passwort'] ?? '');
    if (mb_strlen($pass) < 8) {
        $fehler = 'Passwort muss mindestens 8 Zeichen haben.';
    } else {
        $pdo->prepare("UPDATE tagesmuetter SET passwort_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
            ->execute([tmf_hash_pw($pass), $uid]);
        $ok = true;
    }
}
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Passwort zurücksetzen – Tagesmutter finden</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="brand"><a href="index.html"><img src="img/logo-tagesmutter.png" alt="Tagesmutter finden" style="height:44px"></a></div>
    <h1>Neues Passwort</h1>
    <?php if (!$uid): ?>
      <div class="auth-err">Dieser Link ist ungültig oder abgelaufen.</div>
      <div class="links"><a href="passwort-vergessen.php">Neuen Link anfordern</a></div>
    <?php elseif ($ok): ?>
      <div class="auth-ok">✅ Dein Passwort wurde geändert. Du kannst dich jetzt anmelden.</div>
      <div class="links"><a href="login.php">→ Zum Login</a></div>
    <?php else: ?>
      <?php if ($fehler): ?><div class="auth-err"><?= $e($fehler) ?></div><?php endif; ?>
      <p class="sub">Wähle ein neues Passwort (mind. 8 Zeichen).</p>
      <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= $e($token) ?>">
        <div class="field">
          <label for="pw">Neues Passwort</label>
          <input type="password" id="pw" name="passwort" required minlength="8" autofocus autocomplete="new-password">
        </div>
        <button class="btn btn-coral" type="submit">Passwort speichern</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

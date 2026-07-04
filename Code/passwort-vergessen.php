<?php
declare(strict_types=1);
require __DIR__ . '/api/auth.php';
if (tmf_current_user()) { header('Location: mein-konto.php'); exit; }

$done = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $user = tmf_find_by_email($email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            tmf_db()->prepare("UPDATE tagesmuetter SET reset_token=?, reset_expires=? WHERE id=?")
                ->execute([$token, time() + 3600, $user['id']]);
            $link = 'https://tagesmutter-vergleich.de/passwort-reset.php?token=' . $token;
            $body = "Hallo,\n\nfür dein Konto bei \"Tagesmutter finden\" wurde ein neues Passwort angefordert.\n\n"
                  . "Setze es hier neu (Link 1 Stunde gültig):\n{$link}\n\n"
                  . "Warst du das nicht? Dann ignoriere diese E-Mail – dein Passwort bleibt unverändert.";
            @mail($email, '=?UTF-8?B?' . base64_encode('Passwort zurücksetzen – Tagesmutter finden') . '?=', $body, "Content-Type: text/plain; charset=utf-8\r\n");
        }
    }
    $done = true; // immer gleiche Antwort → verrät nicht, ob die E-Mail existiert
}
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Passwort vergessen – Tagesmutter finden</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="brand"><a href="index.html"><img src="img/logo-tagesmutter.png" alt="Tagesmutter finden" style="height:44px"></a></div>
    <h1>Passwort vergessen</h1>
    <?php if ($done): ?>
      <div class="auth-ok">✅ Falls ein Konto mit dieser E-Mail existiert, haben wir dir einen Link zum Zurücksetzen geschickt. Schau in dein Postfach (auch Spam-Ordner).</div>
      <div class="links"><a href="login.php">← Zurück zum Login</a></div>
    <?php else: ?>
      <p class="sub">Gib deine E-Mail ein – wir schicken dir einen Link zum Zurücksetzen.</p>
      <form method="post" novalidate>
        <div class="field">
          <label for="email">E-Mail</label>
          <input type="email" id="email" name="email" required autofocus autocomplete="email">
        </div>
        <button class="btn btn-coral" type="submit">Link anfordern</button>
      </form>
      <div class="links"><a href="login.php">← Zurück zum Login</a></div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

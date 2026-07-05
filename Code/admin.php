<?php
/**
 * Admin-/Freigabe-Bereich für "Tagesmutter finden".
 * Login mit ADMIN_PASS (aus config.php / GitHub-Secret). Neue Einträge sind
 * zuerst "pending" und werden hier freigegeben, abgelehnt oder gelöscht.
 */
declare(strict_types=1);
session_start();
require __DIR__ . '/api/db.php';

$cfg       = tmf_config();
$adminPass = (string)($cfg['ADMIN_PASS'] ?? 'admin');

// --- Logout ---
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

// --- Login ---
$loginError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login_pass'])) {
    if (hash_equals($adminPass, (string)$_POST['login_pass'])) {
        session_regenerate_id(true);
        $_SESSION['tmf_admin'] = true;
    } else {
        $loginError = 'Falsches Passwort.';
    }
}
$isAdmin = !empty($_SESSION['tmf_admin']);

// --- Aktionen (nur eingeloggt) ---
$hinweis = '';
if ($isAdmin && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $pdo = tmf_db();
    $id  = (string)$_POST['id'];
    if ($_POST['action'] === 'approve') {
        $pdo->prepare("UPDATE tagesmuetter SET status='approved' WHERE id=?")->execute([$id]);
        $hinweis = 'Eintrag freigegeben – ist jetzt öffentlich sichtbar.';
    } elseif ($_POST['action'] === 'reject') {
        $pdo->prepare("UPDATE tagesmuetter SET status='rejected' WHERE id=?")->execute([$id]);
        $hinweis = 'Eintrag abgelehnt (bleibt gespeichert, aber unsichtbar).';
    } elseif ($_POST['action'] === 'delete') {
        $row = $pdo->prepare("SELECT foto, fotos FROM tagesmuetter WHERE id=?");
        $row->execute([$id]);
        if ($rr = $row->fetch()) {
            if ($rr['foto']) @unlink(__DIR__ . '/uploads/' . $rr['foto']);
            foreach (tmf_fotos_list($rr['fotos'] ?? '') as $g) @unlink(__DIR__ . '/uploads/' . $g);
        }
        foreach (['anfragen', 'vormerkungen'] as $t) $pdo->prepare("DELETE FROM {$t} WHERE tm_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM tagesmuetter WHERE id=?")->execute([$id]);
        $hinweis = 'Eintrag endgültig gelöscht.';
    }
    header('Location: admin.php?msg=' . rawurlencode($hinweis));
    exit;
}
if (isset($_GET['msg'])) $hinweis = (string)$_GET['msg'];

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Einträge laden (pending zuerst)
$eintraege = [];
if ($isAdmin) {
    $eintraege = tmf_db()->query(
        "SELECT * FROM tagesmuetter
         ORDER BY CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END, created_at DESC"
    )->fetchAll();
}
$anzOffen = count(array_filter($eintraege, fn($r) => $r['status'] === 'pending'));
$stats = ['tm' => 0, 'anfragen' => 0, 'vormerk' => 0];
if ($isAdmin) {
    $db = tmf_db();
    $stats['tm']         = (int)$db->query("SELECT COUNT(*) FROM tagesmuetter WHERE status='approved'")->fetchColumn();
    $stats['anfragen']   = (int)$db->query("SELECT COUNT(*) FROM anfragen")->fetchColumn();
    $stats['vormerk']    = (int)$db->query("SELECT COUNT(*) FROM vormerkungen")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Admin – Tagesmutter finden</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🧸</text></svg>">
<link rel="stylesheet" href="styles.css">
<style>
  .admin-wrap{max-width:900px;margin:0 auto;padding:2rem 1.4rem 4rem}
  .admin-top{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap}
  .admin-top h1{font-size:1.6rem;font-weight:800}
  .admin-top .count{background:var(--amber-bg);color:var(--amber);font-weight:800;font-size:.85rem;padding:.3rem .8rem;border-radius:999px}
  .admin-top .logout{margin-left:auto;font-size:.85rem;font-weight:700;color:var(--muted);text-decoration:none}
  .login-card{max-width:400px;margin:4rem auto;background:#fff;border:1px solid var(--line);border-radius:20px;padding:2.2rem;box-shadow:var(--shadow);text-align:center}
  .login-card h1{font-size:1.4rem;font-weight:800;margin-bottom:.4rem}
  .login-card p{color:var(--muted);font-size:.9rem;margin-bottom:1.4rem}
  .login-card input{width:100%;border:1.5px solid var(--line);border-radius:12px;padding:.75rem 1rem;font-size:1rem;font-family:inherit;background:var(--cream);margin-bottom:1rem;text-align:center}
  .login-card input:focus{outline:none;border-color:var(--coral)}
  .err{background:#fdeae7;color:var(--coral-dark);font-size:.85rem;font-weight:700;padding:.6rem;border-radius:10px;margin-bottom:1rem}
  .msg{background:var(--sage-bg);color:var(--sage-dark);font-weight:700;font-size:.9rem;padding:.8rem 1.1rem;border-radius:12px;margin-bottom:1.4rem}
  .arow{background:#fff;border:1px solid var(--line);border-radius:16px;padding:1.2rem;margin-bottom:1rem;box-shadow:var(--shadow-sm);display:flex;gap:1.1rem}
  .arow.pending{border-left:5px solid var(--amber)}
  .arow.approved{border-left:5px solid var(--sage)}
  .arow.rejected{border-left:5px solid #c9c1b8;opacity:.65}
  .arow .foto{width:70px;height:70px;border-radius:14px;object-fit:cover;flex-shrink:0;background:var(--cream);display:grid;place-items:center;font-size:1.6rem;color:#fff;font-weight:800}
  .arow .body{flex:1;min-width:0}
  .arow h3{font-size:1.05rem;font-weight:800}
  .arow .sub{color:var(--muted);font-size:.82rem;font-weight:700;margin-bottom:.4rem}
  .arow .txt{font-size:.88rem;color:var(--ink-soft);white-space:pre-line;max-height:5.2em;overflow:hidden}
  .arow .st{display:inline-block;font-size:.72rem;font-weight:800;padding:.15rem .6rem;border-radius:999px;margin-bottom:.4rem}
  .st-pending{background:var(--amber-bg);color:var(--amber)}
  .st-approved{background:var(--sage-bg);color:var(--sage-dark)}
  .st-rejected{background:#eee7e0;color:var(--muted)}
  .arow .acts{display:flex;flex-direction:column;gap:.4rem;flex-shrink:0}
  .arow .acts button{border:none;cursor:pointer;font-weight:800;font-size:.82rem;padding:.5rem .9rem;border-radius:999px;font-family:inherit;white-space:nowrap}
  .b-ok{background:var(--grad-coral);color:#fff}
  .b-mid{background:#f6ecdf;color:var(--ink)}
  .b-del{background:#fff;color:var(--coral-dark);border:1.5px solid #f3d5cf !important}
  .empty{text-align:center;color:var(--muted);padding:3rem;font-weight:700}
  @media(max-width:600px){.arow{flex-wrap:wrap}.arow .acts{flex-direction:row;width:100%}}
  .stat-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.8rem;margin-bottom:1.6rem}
  .stat-k{background:#fff;border:1px solid var(--line);border-radius:14px;padding:1rem;text-align:center;box-shadow:var(--shadow-sm)}
  .stat-k .n{font-size:1.6rem;font-weight:800;color:var(--coral)}
  .stat-k .l{font-size:.78rem;color:var(--muted);font-weight:700}
</style>
</head>
<body>
<?php if (!$isAdmin): ?>
  <div class="login-card">
    <div style="font-size:2.4rem">🧸</div>
    <h1>Admin-Bereich</h1>
    <p>Freigabe der Tagesmütter-Einträge</p>
    <?php if ($loginError): ?><div class="err"><?= $e($loginError) ?></div><?php endif; ?>
    <form method="post">
      <input type="password" name="login_pass" placeholder="Passwort" autofocus required>
      <button type="submit" class="btn btn-coral" style="width:100%">Anmelden</button>
    </form>
    <p style="margin-top:1.4rem"><a href="/" style="color:var(--muted);font-size:.82rem">← Zur Website</a></p>
  </div>
<?php else: ?>
  <div class="admin-wrap">
    <div class="admin-top">
      <h1>🧸 Freigabe</h1>
      <?php if ($anzOffen): ?><span class="count"><?= $anzOffen ?> wartet auf Prüfung</span><?php endif; ?>
      <a class="logout" href="admin.php?logout=1">Abmelden</a>
    </div>
    <?php if ($hinweis): ?><div class="msg"><?= $e($hinweis) ?></div><?php endif; ?>

    <div class="stat-row">
      <div class="stat-k"><div class="n"><?= $stats['tm'] ?></div><div class="l">öffentlich</div></div>
      <div class="stat-k"><div class="n"><?= $anzOffen ?></div><div class="l">wartet auf Prüfung</div></div>
      <div class="stat-k"><div class="n"><?= $stats['anfragen'] ?></div><div class="l">Anfragen</div></div>
      <div class="stat-k"><div class="n"><?= $stats['vormerk'] ?></div><div class="l">Vormerkungen</div></div>
    </div>

    <?php if (!$eintraege): ?>
      <div class="empty">Noch keine Einträge vorhanden.</div>
    <?php else: foreach ($eintraege as $r):
        $alter = json_decode($r['altersgruppen'] ?: '[]', true) ?: [];
        $farben = ['#f2a25c','#6aa87e','#7f9fd1','#d17fa8','#a58bd1','#5cbdb9'];
        $farbe = $farben[array_sum(array_map('ord', str_split($r['name']))) % count($farben)];
    ?>
      <div class="arow <?= $e($r['status']) ?>">
        <?php if ($r['foto']): ?>
          <img class="foto" src="uploads/<?= $e($r['foto']) ?>" alt="">
        <?php else: ?>
          <div class="foto" style="background:<?= $farbe ?>"><?= $e(mb_substr($r['name'],0,1)) ?></div>
        <?php endif; ?>
        <div class="body">
          <span class="st st-<?= $e($r['status']) ?>"><?= ['pending'=>'🕓 Wartet','approved'=>'✓ Freigegeben','rejected'=>'✕ Abgelehnt'][$r['status']] ?? $e($r['status']) ?></span>
          <h3><?= $e($r['name']) ?></h3>
          <div class="sub"><b>Nr. <?= tmf_usernr($r['nummer']) ?></b> · 📍 <?= $e($r['ort']) ?><?= $r['bundesland'] ? ' ('.$e($r['bundesland']).')' : '' ?> · 🕐 <?= $e($r['zeiten']) ?> · <?= $r['plaetze'] > 0 ? $e($r['plaetze']).' Plätze frei' : 'Warteliste' ?> · <?= $e(implode(', ', $alter)) ?><?= $r['erlaubnis'] ? ' · ✓ §43' : '' ?></div>
          <div class="txt"><?= $e($r['persoenlich']) ?></div>
          <div class="sub" style="margin-top:.4rem">✉️ <?= $e($r['email']) ?><?= $r['tel'] ? ' · 📞 '.$e($r['tel']) : '' ?></div>
        </div>
        <div class="acts">
          <a class="b-mid" href="admin-bearbeiten.php?id=<?= $e($r['id']) ?>" style="text-decoration:none;text-align:center">✏️ Bearbeiten</a>
          <?php if ($r['status'] !== 'approved'): ?>
            <form method="post"><input type="hidden" name="id" value="<?= $e($r['id']) ?>"><button class="b-ok" name="action" value="approve">✓ Freigeben</button></form>
          <?php endif; ?>
          <?php if ($r['status'] === 'pending'): ?>
            <form method="post"><input type="hidden" name="id" value="<?= $e($r['id']) ?>"><button class="b-mid" name="action" value="reject">Ablehnen</button></form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('Diesen Eintrag endgültig löschen?')"><input type="hidden" name="id" value="<?= $e($r['id']) ?>"><button class="b-del" name="action" value="delete">Löschen</button></form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
<?php endif; ?>
</body>
</html>

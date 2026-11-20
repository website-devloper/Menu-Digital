<?php
require __DIR__ . '/../shared/config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        $_SESSION['uid'] = (int)$u['id'];
        header('Location: /admin/');
        exit;
    }
    $error = 'Identifiants invalides.';
}
$csrf = csrf_token();
?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><title>Connexion · Wevtex Menu</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/shared/theme.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { display:grid; place-items:center; min-height:100vh; padding: 20px;
    background: radial-gradient(1200px 600px at 10% -10%, rgba(241,172,24,.14), transparent 60%),
                radial-gradient(1000px 500px at 110% 110%, rgba(136,58,97,.14), transparent 60%),
                var(--light);
  }
  .login {
    background: var(--surface); border:1px solid var(--border); border-radius: var(--radius-xl);
    box-shadow: var(--shadow-card); width: 100%; max-width: 420px; padding: 36px 32px;
  }
  .brand { display:flex; align-items:center; gap: 12px; margin-bottom: 24px;}
  .brand-mark {
    width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--gold),var(--amber));
    display:grid;place-items:center;color:#241017;font-family:var(--font-heading);font-weight:800;font-size:20px;
    box-shadow: var(--shadow-gold);
  }
  .brand h2 { font-size: 18px; font-weight: 800; }
  .brand p  { font-size: 12px; color: var(--muted-ink); letter-spacing: .08em; text-transform: uppercase; font-weight: 700;}
  h1.title { font-size: 26px; font-weight: 800; margin-bottom: 4px; }
  .sub { color: var(--muted-ink); font-size: 14px; margin-bottom: 24px; }
  .field { margin-bottom: 14px; }
  .err { color: var(--bad-ink); background: var(--bad-bg); padding: 10px 12px; border-radius: var(--radius-md); font-size: 13px; margin-bottom: 14px; font-weight: 600;}
  .hint { font-size: 12px; color: var(--muted-ink); margin-top: 18px; text-align:center;}
</style>
</head><body>
<div class="login">
  <div class="brand">
    <div class="brand-mark">W</div>
    <div>
      <h2>Wevtex · Menu</h2>
      <p>Dashboard restaurant</p>
    </div>
  </div>
  <h1 class="title">Bienvenue</h1>
  <div class="sub">Connectez-vous pour gérer votre menu digital.</div>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="field"><label class="label">Email</label>
      <input class="input" type="email" name="email" required autofocus></div>
    <div class="field"><label class="label">Mot de passe</label>
      <input class="input" type="password" name="password" required></div>
    <button class="btn-primary" style="width:100%;margin-top:8px;">Se connecter</button>
  </form>
  <div class="hint">Démo : <b>admin@foores.ma</b> / <b>admin1234</b></div>
</div>
</body></html>

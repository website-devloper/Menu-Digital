<?php
require __DIR__ . '/shared/config.php';
require __DIR__ . '/shared/lib.php';

$slug = $_GET['r'] ?? '';
$rest = restaurant_by_slug($slug);
if (!$rest) { http_response_code(404); exit('Restaurant introuvable.'); }
$rid = (int)$rest['id'];

$ok = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $name  = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $size  = (int)($_POST['party_size'] ?? 0);
        $date  = (string)($_POST['date'] ?? '');
        $time  = (string)($_POST['time'] ?? '');
        $notes = trim((string)($_POST['notes'] ?? ''));
        if ($name === '' || $phone === '' || $size <= 0 || $date === '' || $time === '') {
            throw new RuntimeException('Merci de remplir tous les champs obligatoires.');
        }
        if ($size > 20)  throw new RuntimeException('Pour les groupes de plus de 20 personnes, appelez-nous directement.');
        if ($date < date('Y-m-d')) throw new RuntimeException('La date doit être aujourd\'hui ou plus tard.');

        db()->prepare('INSERT INTO reservations (restaurant_id, name, phone, email, party_size, date, time, notes) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$rid, $name, $phone, $email ?: null, $size, $date, $time, $notes ?: null]);
        $ok = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$csrf = csrf_token();
[$hStatus, $hLabel] = hours_status(hours_decode($rest['hours']));
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Réserver · <?= e($rest['name']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#2A1324">
<link rel="stylesheet" href="/shared/theme.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,500,0,0&display=swap" rel="stylesheet">
<style>
  body { background:
    radial-gradient(1000px 500px at 110% -10%, rgba(241,172,24,.12), transparent 60%),
    radial-gradient(900px 500px at -10% 110%, rgba(136,58,97,.14), transparent 60%),
    var(--light);
    color: var(--dark); min-height: 100vh;
  }
  .wrap { max-width: 620px; margin: 0 auto; padding: 48px 20px 60px;}
  .back { display: inline-flex; align-items: center; gap: 7px; font-size: 13px; color: var(--muted-ink); font-weight: 700; margin-bottom: 20px;}
  .back:hover { color: var(--accent);}
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 36px 32px; box-shadow: 0 1px 2px rgba(42,19,36,.05), 0 30px 70px rgba(42,19,36,.14), inset 0 1px 0 rgba(255,255,255,.4);}
  @media (min-width: 640px) { .card { padding: 48px 48px;} }

  .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 20px;}
  .brand .mark { width: 46px; height: 46px; border-radius: 13px; background: linear-gradient(135deg, var(--gold), var(--amber)); display: grid; place-items: center; color: var(--dark); font-weight: 900; font-size: 20px; box-shadow: 0 10px 24px rgba(241,172,24,.35), inset 0 0 0 1px rgba(255,255,255,.4);}
  .brand h2 { font-weight: 900; font-size: 19px; letter-spacing: -.02em;}
  .brand p { font-size: 11px; color: var(--accent); letter-spacing: .18em; text-transform: uppercase; font-weight: 900;}

  h1 { font-size: 32px; font-weight: 900; letter-spacing: -.025em; margin-bottom: 8px;}
  .lede { color: var(--muted-ink); font-size: 14.5px; font-weight: 600; margin-bottom: 24px;}
  .hours-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; margin-bottom: 18px;}
  .hours-chip.open { background: var(--ok-bg); color: var(--ok-ink);}
  .hours-chip.closing_soon { background: rgba(241,172,24,.14); color: #8b5a00;}
  .hours-chip.closed { background: var(--bad-bg); color: var(--bad-ink);}
  .hours-chip::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: currentColor;}

  form .row { display: grid; grid-template-columns: 1fr; gap: 14px; margin-bottom: 14px;}
  @media (min-width: 540px) { form .row.cols-2 { grid-template-columns: 1fr 1fr;} }
  form .field label { display: block; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: var(--muted-ink); margin-bottom: 7px;}
  form .field input, form .field textarea, form .field select { width: 100%; padding: 12px 14px; background: var(--surface); border: 1px solid var(--border-strong); border-radius: 12px; font-family: inherit; font-size: 14px; font-weight: 500; color: var(--dark); transition: var(--transition);}
  form .field input:focus, form .field textarea:focus, form .field select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(136,58,97,.12);}
  form .field textarea { min-height: 80px; resize: vertical;}
  form .submit { margin-top: 10px; padding: 14px 24px; font-size: 14px; letter-spacing: .04em; width: 100%;}

  .err { background: var(--bad-bg); color: var(--bad-ink); padding: 12px 14px; border-radius: 12px; font-size: 13.5px; font-weight: 700; margin-bottom: 14px;}

  .success { text-align: center; padding: 20px 0;}
  .success .big { font-size: 56px; margin-bottom: 14px;}
  .success h3 { font-size: 24px; font-weight: 800; letter-spacing: -.02em; margin-bottom: 8px;}
  .success p { color: var(--muted-ink); font-size: 14.5px; line-height: 1.6; margin-bottom: 22px;}
  .success .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;}

  .foot { text-align: center; margin-top: 22px; font-size: 11px; letter-spacing: .3em; text-transform: uppercase; color: var(--muted-ink); font-weight: 800;}
</style>
</head>
<body>
<div class="wrap">
  <a class="back" href="/menu.php?r=<?= e($rest['slug']) ?>"><span class="msi sm" aria-hidden="true">arrow_back</span> Retour au menu</a>

  <div class="card">
    <div class="brand">
      <div class="mark"><?= e(mb_substr($rest['name'], 0, 1)) ?></div>
      <div><h2><?= e($rest['name']) ?></h2><p>Réservation</p></div>
    </div>

    <?php if ($ok): ?>
      <div class="success">
        <div class="big"><span class="msi" style="font-size:72px; color: var(--green-2); font-variation-settings:'FILL' 1,'wght' 700,'GRAD' 0,'opsz' 48;" aria-hidden="true">task_alt</span></div>
        <h3>Demande envoyée !</h3>
        <p>Nous vous contacterons rapidement pour confirmer. Un sms ou un appel au numéro indiqué.</p>
        <div class="actions">
          <a class="btn-primary" href="/menu.php?r=<?= e($rest['slug']) ?>">Retour au menu</a>
          <?php if ($rest['phone']): ?>
            <a class="btn-ghost" href="<?= e(wa_url(wa_number($rest['phone']), 'Bonjour, je viens de faire une réservation.')) ?>" target="_blank">Nous joindre</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <h1>Réserver une table</h1>
      <div class="lede">Indiquez votre demande, nous vous recontactons pour confirmer.</div>
      <div class="hours-chip <?= e($hStatus) ?>"><?= e($hLabel) ?></div>

      <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="row cols-2">
          <div class="field"><label>Nom et prénom *</label><input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>"></div>
          <div class="field"><label>Téléphone *</label><input type="tel" name="phone" required placeholder="06 12 34 56 78" value="<?= e($_POST['phone'] ?? '') ?>"></div>
        </div>
        <div class="row">
          <div class="field"><label>Email (optionnel)</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"></div>
        </div>
        <div class="row cols-2">
          <div class="field"><label>Date *</label><input type="date" name="date" required min="<?= date('Y-m-d') ?>" value="<?= e($_POST['date'] ?? date('Y-m-d')) ?>"></div>
          <div class="field"><label>Heure *</label><input type="time" name="time" required value="<?= e($_POST['time'] ?? '20:00') ?>"></div>
        </div>
        <div class="row cols-2">
          <div class="field"><label>Nombre de personnes *</label>
            <select name="party_size" required>
              <?php for ($i = 1; $i <= 12; $i++): ?>
                <option value="<?= $i ?>" <?= ($i === (int)($_POST['party_size'] ?? 2)) ? 'selected' : '' ?>><?= $i ?> personne<?= $i > 1 ? 's' : '' ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="row">
          <div class="field"><label>Message (optionnel)</label><textarea name="notes" placeholder="Allergies, occasion, demande particulière…"><?= e($_POST['notes'] ?? '') ?></textarea></div>
        </div>
        <button class="btn-primary submit" type="submit">Envoyer la demande</button>
      </form>
    <?php endif; ?>

    <div class="foot">★ <?= e($rest['name']) ?> · Menu Digital ★</div>
  </div>
</div>
</body></html>

<?php
require __DIR__ . '/../shared/config.php';
require __DIR__ . '/../shared/lib.php';
$user = require_auth();
$rest = current_restaurant($user);

$DAYS = ['mon'=>'Lundi','tue'=>'Mardi','wed'=>'Mercredi','thu'=>'Jeudi','fri'=>'Vendredi','sat'=>'Samedi','sun'=>'Dimanche'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $name    = trim((string)($_POST['name'] ?? ''));
        $tagline = trim((string)($_POST['tagline'] ?? ''));
        $phone   = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $currency= trim((string)($_POST['currency'] ?? 'DH'));

        $hours = [];
        foreach (array_keys($DAYS) as $d) {
            $closed = !empty($_POST["closed_$d"]);
            $open   = trim((string)($_POST["open_$d"]  ?? ''));
            $close  = trim((string)($_POST["close_$d"] ?? ''));
            $hours[$d] = ['closed' => $closed ? 1 : 0, 'open' => $open, 'close' => $close];
        }

        db()->prepare('UPDATE restaurants SET name = ?, tagline = ?, phone = ?, address = ?, currency = ?, hours = ? WHERE id = ?')
            ->execute([$name, $tagline, $phone, $address, $currency ?: 'DH', json_encode($hours), $rest['id']]);
        header('Location: /admin/settings.php?ok=' . rawurlencode('Paramètres enregistrés.'));
        exit;
    } catch (Throwable $e) {
        header('Location: /admin/settings.php?err=' . rawurlencode($e->getMessage()));
        exit;
    }
}

$hours = hours_decode($rest['hours']);

$PAGE_TITLE = 'Paramètres';
$ACTIVE     = 'settings';
$csrf       = csrf_token();
require __DIR__ . '/../shared/layout.php';
?>

<div class="page-head">
  <div><h1>Paramètres</h1><div class="sub">Informations du restaurant et horaires d'ouverture.</div></div>
</div>

<form method="post">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <div class="card" style="padding: 24px; margin-bottom: 20px;">
    <h2 style="font-size:16px;font-weight:800;margin-bottom:16px;">Informations</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div><label class="label">Nom du restaurant</label>
        <input class="input" name="name" value="<?= e($rest['name']) ?>" required></div>
      <div><label class="label">Devise</label>
        <input class="input" name="currency" value="<?= e($rest['currency']) ?>" maxlength="4"></div>
      <div style="grid-column: 1 / -1;"><label class="label">Slogan</label>
        <input class="input" name="tagline" value="<?= e($rest['tagline']) ?>" placeholder="Cuisine marocaine contemporaine"></div>
      <div><label class="label">Téléphone (format international, sans +)</label>
        <input class="input" name="phone" value="<?= e($rest['phone']) ?>" placeholder="212600000000"></div>
      <div><label class="label">Adresse</label>
        <input class="input" name="address" value="<?= e($rest['address']) ?>" placeholder="12 rue des Jardins, Marrakech"></div>
    </div>
    <div style="margin-top:12px;color:var(--muted-ink);font-size:12.5px;">
      Le téléphone est utilisé pour les boutons <b>Commander via WhatsApp</b>. Format E.164 (ex: <code>212600000000</code>).
    </div>
  </div>

  <div class="card" style="padding: 24px; margin-bottom: 20px;">
    <h2 style="font-size:16px;font-weight:800;margin-bottom:16px;">Horaires d'ouverture</h2>
    <div style="display:grid;gap:10px;">
      <?php foreach ($DAYS as $k => $label):
        $d = $hours[$k];
      ?>
        <div style="display:grid;grid-template-columns:120px 100px 140px 20px 140px;gap:12px;align-items:center;">
          <div style="font-weight:700;"><?= e($label) ?></div>
          <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
            <input type="checkbox" name="closed_<?= e($k) ?>" <?= !empty($d['closed']) ? 'checked' : '' ?> style="accent-color: var(--gold);">
            <span style="font-size:13px;">Fermé</span>
          </label>
          <input class="input" type="time" name="open_<?= e($k) ?>"  value="<?= e($d['open']) ?>" style="padding:8px 10px;">
          <span style="text-align:center;color:var(--muted-ink);">—</span>
          <input class="input" type="time" name="close_<?= e($k) ?>" value="<?= e($d['close']) ?>" style="padding:8px 10px;">
        </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:12px;color:var(--muted-ink);font-size:12.5px;">Minuit se saisit comme <code>00:00</code>. Ces horaires alimentent la bannière "Nous servons jusqu'à…" sur le menu public.</div>
  </div>

  <button class="btn-primary">Enregistrer</button>
</form>

<?php require __DIR__ . '/../shared/layout_foot.php';

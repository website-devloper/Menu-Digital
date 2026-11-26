<?php
require __DIR__ . '/../shared/config.php';
require __DIR__ . '/../shared/lib.php';
$user = require_auth();
$rest = current_restaurant($user);

$DAYS = ['mon'=>'Lundi','tue'=>'Mardi','wed'=>'Mercredi','thu'=>'Jeudi','fri'=>'Vendredi','sat'=>'Samedi','sun'=>'Dimanche'];

function save_logo_upload(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $info = getimagesize($file['tmp_name']);
    if (!$info) throw new RuntimeException('Fichier image invalide.');
    $extMap = [IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_WEBP => '.webp', IMAGETYPE_GIF => '.gif', IMAGETYPE_SVG => '.svg'];
    if (!isset($extMap[$info[2]])) throw new RuntimeException('Format non supporté (jpg/png/webp/gif).');
    if ($file['size'] > 4 * 1024 * 1024) throw new RuntimeException('Logo trop lourd (max 4 Mo).');
    $name = 'logo_' . bin2hex(random_bytes(6)) . $extMap[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . DIRECTORY_SEPARATOR . $name)) {
        throw new RuntimeException('Échec de téléchargement.');
    }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $name    = trim((string)($_POST['name'] ?? ''));
        $tagline = trim((string)($_POST['tagline'] ?? ''));
        $phone   = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $currency= trim((string)($_POST['currency'] ?? 'DH'));

        $logo = $rest['logo'] ?? null;
        if (!empty($_POST['remove_logo'])) {
            $logo = null;
        } elseif (!empty($_FILES['logo']['name'])) {
            $uploaded = save_logo_upload($_FILES['logo']);
            if ($uploaded) $logo = $uploaded;
        }

        $hours = [];
        foreach (array_keys($DAYS) as $d) {
            $closed = !empty($_POST["closed_$d"]);
            $open   = trim((string)($_POST["open_$d"]  ?? ''));
            $close  = trim((string)($_POST["close_$d"] ?? ''));
            $hours[$d] = ['closed' => $closed ? 1 : 0, 'open' => $open, 'close' => $close];
        }

        db()->prepare('UPDATE restaurants SET name = ?, tagline = ?, phone = ?, address = ?, currency = ?, logo = ?, hours = ? WHERE id = ?')
            ->execute([$name, $tagline, $phone, $address, $currency ?: 'DH', $logo, json_encode($hours), $rest['id']]);
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

<?php if (!empty($_GET['ok'])): ?>
  <div class="card" style="padding:14px 18px;margin-bottom:16px;background:var(--ok-bg);color:var(--ok-ink);border-color:transparent;font-weight:700;"><?= e($_GET['ok']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['err'])): ?>
  <div class="card" style="padding:14px 18px;margin-bottom:16px;background:var(--bad-bg);color:var(--bad-ink);border-color:transparent;font-weight:700;"><?= e($_GET['err']) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <div class="card" style="padding: 24px; margin-bottom: 20px;">
    <h2 style="font-size:16px;font-weight:800;margin-bottom:16px;">Logo du restaurant</h2>
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
      <div style="width:110px;height:110px;border-radius:16px;background:var(--dark);display:grid;place-items:center;overflow:hidden;border:1px solid var(--border-strong);box-shadow:var(--shadow-card);flex-shrink:0;">
        <?php if (!empty($rest['logo'])): ?>
          <img src="/uploads/<?= e($rest['logo']) ?>" alt="Logo" style="max-width:88px;max-height:88px;width:auto;height:auto;object-fit:contain;">
        <?php else: ?>
          <img src="/assets/wevtex-mark-light.png" alt="Logo Wevtex (défaut)" style="max-width:70px;opacity:.55;">
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:240px;">
        <label class="label">Changer le logo</label>
        <input class="input" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
        <div style="margin-top:8px;color:var(--muted-ink);font-size:12.5px;">
          PNG transparent recommandé (carré ou large). Max 4 Mo. Le logo apparaît dans le pied de page du menu public.
        </div>
        <?php if (!empty($rest['logo'])): ?>
          <label style="display:inline-flex;align-items:center;gap:7px;margin-top:10px;cursor:pointer;color:var(--bad-ink);font-weight:700;font-size:13px;">
            <input type="checkbox" name="remove_logo" value="1" style="accent-color:var(--red);">
            Retirer le logo actuel
          </label>
        <?php endif; ?>
      </div>
    </div>
  </div>

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

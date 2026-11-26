<?php
require __DIR__ . '/../shared/config.php';
$user = require_auth();
$rest = current_restaurant($user);
$rid  = (int)$rest['id'];

$DAYS = [1=>'Lun',2=>'Mar',4=>'Mer',8=>'Jeu',16=>'Ven',32=>'Sam',64=>'Dim'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $a = $_POST['action'] ?? '';
        if ($a === 'save') {
            $id        = (int)($_POST['id'] ?? 0);
            $name      = trim((string)($_POST['name'] ?? ''));
            $scopeType = $_POST['scope_type'] ?? 'all';
            $scopeId   = (int)($_POST['scope_id'] ?? 0) ?: null;
            $disc      = max(0, min(100, (int)($_POST['discount_pct'] ?? 0)));
            $sD        = trim((string)($_POST['start_date'] ?? '')) ?: null;
            $eD        = trim((string)($_POST['end_date']   ?? '')) ?: null;
            $sT        = trim((string)($_POST['start_time'] ?? '')) ?: null;
            $eT        = trim((string)($_POST['end_time']   ?? '')) ?: null;
            $mask      = 0;
            foreach (array_keys($DAYS) as $bit) if (!empty($_POST['day_' . $bit])) $mask |= $bit;
            if ($mask === 0) $mask = 127;
            $active    = isset($_POST['active']) ? 1 : 0;
            if ($name === '') throw new RuntimeException('Nom requis.');
            if (!in_array($scopeType, ['all','category','dish'], true)) throw new RuntimeException('Portée invalide.');
            if ($scopeType !== 'all' && !$scopeId) throw new RuntimeException('Choisissez la cible.');

            if ($id > 0) {
                db()->prepare('UPDATE promotions SET name=?, scope_type=?, scope_id=?, discount_pct=?, start_date=?, end_date=?, start_time=?, end_time=?, days_mask=?, active=? WHERE id=? AND restaurant_id=?')
                    ->execute([$name,$scopeType,$scopeId,$disc,$sD,$eD,$sT,$eT,$mask,$active,$id,$rid]);
            } else {
                db()->prepare('INSERT INTO promotions (restaurant_id, name, scope_type, scope_id, discount_pct, start_date, end_date, start_time, end_time, days_mask, active) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$rid,$name,$scopeType,$scopeId,$disc,$sD,$eD,$sT,$eT,$mask,$active]);
            }
            header('Location: /admin/promotions.php?ok=' . rawurlencode('Promotion enregistrée.'));
            exit;
        }
        if ($a === 'toggle') {
            db()->prepare('UPDATE promotions SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ? AND restaurant_id = ?')
                ->execute([(int)$_POST['id'], $rid]);
            header('Location: /admin/promotions.php');
            exit;
        }
        if ($a === 'delete') {
            db()->prepare('DELETE FROM promotions WHERE id = ? AND restaurant_id = ?')
                ->execute([(int)$_POST['id'], $rid]);
            header('Location: /admin/promotions.php?ok=' . rawurlencode('Promotion supprimée.'));
            exit;
        }
    } catch (Throwable $e) {
        header('Location: /admin/promotions.php?err=' . rawurlencode($e->getMessage()));
        exit;
    }
}

$cats = db()->prepare('SELECT id, name FROM categories WHERE restaurant_id = ? ORDER BY position');
$cats->execute([$rid]);
$cats = $cats->fetchAll();

$dishes = db()->prepare('SELECT id, name FROM dishes WHERE restaurant_id = ? ORDER BY name');
$dishes->execute([$rid]);
$dishes = $dishes->fetchAll();

$list = db()->prepare('SELECT * FROM promotions WHERE restaurant_id = ? ORDER BY active DESC, id DESC');
$list->execute([$rid]);
$promos = $list->fetchAll();

$editing = null;
if (!empty($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM promotions WHERE id = ? AND restaurant_id = ?');
    $s->execute([(int)$_GET['edit'], $rid]);
    $editing = $s->fetch() ?: null;
}
$isForm = !empty($_GET['new']) || $editing;

$PAGE_TITLE = 'Promotions';
$ACTIVE     = 'promotions';
$csrf       = csrf_token();
require __DIR__ . '/../shared/layout.php';

$resolveTarget = function(array $p) use ($cats, $dishes) {
    if ($p['scope_type'] === 'all') return 'Toute la carte';
    if ($p['scope_type'] === 'category') {
        foreach ($cats as $c) if ((int)$c['id'] === (int)$p['scope_id']) return 'Catégorie : ' . $c['name'];
    }
    if ($p['scope_type'] === 'dish') {
        foreach ($dishes as $d) if ((int)$d['id'] === (int)$p['scope_id']) return 'Plat : ' . $d['name'];
    }
    return '—';
};
?>

<div class="page-head">
  <div><h1>Promotions</h1><div class="sub">Réductions planifiées : happy hour, week-end, saison...</div></div>
  <?php if (!$isForm): ?><a class="btn-primary" href="/admin/promotions.php?new=1">+ Nouvelle promotion</a><?php endif; ?>
</div>

<?php if ($isForm): ?>
<div class="card" style="padding: 24px; margin-bottom: 24px;">
  <h2 style="font-size:18px;font-weight:800;margin-bottom:16px;"><?= $editing ? 'Modifier la promotion' : 'Nouvelle promotion' ?></h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div style="grid-column: 1 / -1;"><label class="label">Nom</label>
        <input class="input" name="name" required value="<?= e($editing['name'] ?? '') ?>" placeholder="Happy hour pizzas"></div>

      <div><label class="label">Portée</label>
        <select class="select" name="scope_type" id="scopeType" onchange="toggleScope()">
          <?php foreach ([['all','Toute la carte'],['category','Catégorie'],['dish','Plat spécifique']] as $s): ?>
            <option value="<?= e($s[0]) ?>" <?= ($editing && $editing['scope_type'] === $s[0]) ? 'selected' : '' ?>><?= e($s[1]) ?></option>
          <?php endforeach; ?>
        </select></div>

      <div id="catTarget"><label class="label">Catégorie</label>
        <select class="select" name="scope_id">
          <option value="">—</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($editing && (int)$editing['scope_id'] === (int)$c['id'] && $editing['scope_type'] === 'category') ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select></div>

      <div id="dishTarget"><label class="label">Plat</label>
        <select class="select" name="scope_id_dish">
          <option value="">—</option>
          <?php foreach ($dishes as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= ($editing && (int)$editing['scope_id'] === (int)$d['id'] && $editing['scope_type'] === 'dish') ? 'selected' : '' ?>><?= e($d['name']) ?></option>
          <?php endforeach; ?>
        </select></div>

      <div><label class="label">Réduction (%)</label>
        <input class="input" type="number" min="0" max="100" name="discount_pct" value="<?= e($editing['discount_pct'] ?? 10) ?>" required></div>

      <div style="grid-column: 1 / -1;">
        <label class="label">Jours de la semaine</label>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php $mask = (int)($editing['days_mask'] ?? 127); foreach ($DAYS as $bit => $lbl): ?>
            <label style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;border:1px solid var(--border-strong);cursor:pointer;">
              <input type="checkbox" name="day_<?= $bit ?>" <?= ($mask & $bit) ? 'checked' : '' ?> style="accent-color:var(--gold);">
              <span style="font-weight:700;"><?= e($lbl) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div><label class="label">Date début</label><input class="input" type="date" name="start_date" value="<?= e($editing['start_date'] ?? '') ?>"></div>
      <div><label class="label">Date fin</label><input class="input" type="date" name="end_date"   value="<?= e($editing['end_date']   ?? '') ?>"></div>
      <div><label class="label">Heure début</label><input class="input" type="time" name="start_time" value="<?= e($editing['start_time'] ?? '') ?>"></div>
      <div><label class="label">Heure fin</label>  <input class="input" type="time" name="end_time"   value="<?= e($editing['end_time']   ?? '') ?>"></div>

      <div style="grid-column: 1 / -1;display:flex;align-items:center;gap:10px;padding:12px;background:var(--surface-2);border-radius:var(--radius-md);border:1px solid var(--border);">
        <input type="checkbox" id="pactive" name="active" <?= (!$editing || (int)$editing['active'] === 1) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--gold);">
        <label for="pactive" style="cursor:pointer;"><b>Active</b> — applique la réduction sur le menu public</label>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:20px;">
      <button class="btn-primary">Enregistrer</button>
      <a class="btn-ghost" href="/admin/promotions.php">Annuler</a>
    </div>
  </form>
</div>
<script>
function toggleScope() {
  const t = document.getElementById('scopeType').value;
  document.getElementById('catTarget').style.display  = (t === 'category') ? '' : 'none';
  document.getElementById('dishTarget').style.display = (t === 'dish')     ? '' : 'none';
  // Reassign the right hidden input name
  const dish = document.querySelector('[name="scope_id_dish"]');
  const cat  = document.querySelector('#catTarget select');
  if (t === 'dish') { dish.name = 'scope_id'; cat.name  = 'scope_id_cat'; }
  else              { cat.name  = 'scope_id'; dish.name = 'scope_id_dish'; }
}
toggleScope();
</script>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead><tr><th>Nom</th><th>Cible</th><th>Réduction</th><th>Fenêtre</th><th>État</th><th style="width:140px;text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php if (!$promos): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px 16px;color:var(--muted-ink);">Aucune promotion. <a style="color:var(--accent);font-weight:700;" href="/admin/promotions.php?new=1">Créer la première</a></td></tr>
      <?php else: foreach ($promos as $p):
        $days = [];
        foreach ($DAYS as $bit => $lbl) if ((int)$p['days_mask'] & $bit) $days[] = $lbl;
        $win = ($p['start_time'] || $p['end_time']) ? (($p['start_time'] ?: '00:00') . '–' . ($p['end_time'] ?: '00:00')) : 'Toute la journée';
      ?>
        <tr>
          <td><b><?= e($p['name']) ?></b></td>
          <td><?= e($resolveTarget($p)) ?></td>
          <td><span class="badge badge-gold">−<?= (int)$p['discount_pct'] ?>%</span></td>
          <td style="font-size:13px;color:var(--muted-ink);">
            <?= e(implode('·', $days)) ?><br>
            <?= e($win) ?>
          </td>
          <td>
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="badge <?= (int)$p['active'] === 1 ? 'badge-ok' : 'badge-bad' ?>" style="border:none;cursor:pointer;">
                <?= (int)$p['active'] === 1 ? 'Active' : 'Inactive' ?>
              </button>
            </form>
          </td>
          <td class="row-actions">
            <a class="icon-btn" href="/admin/promotions.php?edit=<?= (int)$p['id'] ?>" title="Modifier"><i class="fa-solid fa-pen"></i></a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette promotion ?');">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="icon-btn danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../shared/layout_foot.php';

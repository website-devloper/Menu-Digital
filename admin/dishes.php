<?php
require __DIR__ . '/../shared/config.php';
require __DIR__ . '/../shared/lib.php';
$user = require_auth();
$rest = current_restaurant($user);
$rid  = (int)$rest['id'];

$ALL_FLAGS = ['vegan','vegetarien','halal','gluten-free','gluten','lactose-free','lactose','nuts'];

function save_upload(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $info = getimagesize($file['tmp_name']);
    if (!$info) throw new RuntimeException('Fichier image invalide.');
    $extMap = [IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_WEBP => '.webp', IMAGETYPE_GIF => '.gif'];
    if (!isset($extMap[$info[2]])) throw new RuntimeException('Format non supporté (jpg/png/webp/gif).');
    if ($file['size'] > 6 * 1024 * 1024) throw new RuntimeException('Image trop lourde (max 6 Mo).');
    $name = bin2hex(random_bytes(8)) . $extMap[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . DIRECTORY_SEPARATOR . $name)) {
        throw new RuntimeException('Échec de téléchargement.');
    }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id   = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $price= (float)str_replace(',', '.', (string)($_POST['price'] ?? '0'));
            $cat  = (int)($_POST['category_id'] ?? 0) ?: null;
            $avail= isset($_POST['available']) ? 1 : 0;
            $spice= max(0, min(3, (int)($_POST['spice'] ?? 0)));
            $special = isset($_POST['is_special']) ? 1 : 0;
            $flags = implode(',', array_values(array_filter($ALL_FLAGS, fn($f) => !empty($_POST['flag_' . str_replace('-', '_', $f)]))));

            if ($name === '') throw new RuntimeException('Nom requis.');
            if ($price < 0) throw new RuntimeException('Prix invalide.');

            $photo = null;
            if (!empty($_FILES['photo']['name'])) $photo = save_upload($_FILES['photo']);

            if ($id > 0) {
                if ($photo) {
                    db()->prepare('UPDATE dishes SET name=?, description=?, price=?, category_id=?, available=?, photo=?, flags=?, spice=?, is_special=? WHERE id=? AND restaurant_id=?')
                        ->execute([$name,$desc,$price,$cat,$avail,$photo,$flags,$spice,$special,$id,$rid]);
                } else {
                    db()->prepare('UPDATE dishes SET name=?, description=?, price=?, category_id=?, available=?, flags=?, spice=?, is_special=? WHERE id=? AND restaurant_id=?')
                        ->execute([$name,$desc,$price,$cat,$avail,$flags,$spice,$special,$id,$rid]);
                }
                header('Location: /admin/dishes.php?edit=' . $id . '&ok=' . rawurlencode('Plat mis à jour.'));
            } else {
                $pos = (int)(db()->query("SELECT COALESCE(MAX(position),-1)+1 FROM dishes WHERE restaurant_id = $rid")->fetchColumn());
                db()->prepare('INSERT INTO dishes (restaurant_id, category_id, name, description, price, photo, available, position, flags, spice, is_special) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$rid, $cat, $name, $desc, $price, $photo, $avail, $pos, $flags, $spice, $special]);
                header('Location: /admin/dishes.php?ok=' . rawurlencode('Plat ajouté.'));
            }
            exit;
        }
        if ($action === 'add_photo') {
            $did = (int)($_POST['dish_id'] ?? 0);
            // confirm ownership
            $own = db()->prepare('SELECT id FROM dishes WHERE id = ? AND restaurant_id = ?');
            $own->execute([$did, $rid]);
            if (!$own->fetch()) throw new RuntimeException('Plat introuvable.');
            if (empty($_FILES['photo']['name'])) throw new RuntimeException('Aucune image sélectionnée.');
            $qc = db()->prepare('SELECT COUNT(*) FROM dish_photos WHERE dish_id = ?'); $qc->execute([$did]);
            if ((int)$qc->fetchColumn() >= 4) throw new RuntimeException('Maximum 4 photos par plat.');
            $fname = save_upload($_FILES['photo']);
            $qp = db()->prepare('SELECT COALESCE(MAX(position),-1)+1 FROM dish_photos WHERE dish_id = ?'); $qp->execute([$did]);
            db()->prepare('INSERT INTO dish_photos (dish_id, photo, position) VALUES (?,?,?)')
                ->execute([$did, $fname, (int)$qp->fetchColumn()]);
            header('Location: /admin/dishes.php?edit=' . $did . '&ok=' . rawurlencode('Photo ajoutée à la galerie.'));
            exit;
        }
        if ($action === 'del_photo') {
            $pid = (int)($_POST['photo_id'] ?? 0);
            $did = (int)($_POST['dish_id'] ?? 0);
            db()->prepare('DELETE FROM dish_photos WHERE id = ? AND dish_id IN (SELECT id FROM dishes WHERE restaurant_id = ?)')
                ->execute([$pid, $rid]);
            header('Location: /admin/dishes.php?edit=' . $did);
            exit;
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            db()->prepare('DELETE FROM dishes WHERE id = ? AND restaurant_id = ?')->execute([$id, $rid]);
            header('Location: /admin/dishes.php?ok=' . rawurlencode('Plat supprimé.'));
            exit;
        }
        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            db()->prepare('UPDATE dishes SET available = CASE WHEN available = 1 THEN 0 ELSE 1 END WHERE id = ? AND restaurant_id = ?')->execute([$id, $rid]);
            header('Location: /admin/dishes.php');
            exit;
        }
    } catch (Throwable $e) {
        header('Location: /admin/dishes.php?err=' . rawurlencode($e->getMessage()));
        exit;
    }
}

// Load data
$cats = db()->prepare('SELECT * FROM categories WHERE restaurant_id = ? ORDER BY position, id');
$cats->execute([$rid]);
$cats = $cats->fetchAll();

$filterCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search    = trim((string)($_GET['q'] ?? ''));

$sql = 'SELECT d.*, c.name AS cat FROM dishes d LEFT JOIN categories c ON c.id = d.category_id WHERE d.restaurant_id = ?';
$params = [$rid];
if ($filterCat > 0) { $sql .= ' AND d.category_id = ?'; $params[] = $filterCat; }
if ($search !== '') { $sql .= ' AND d.name LIKE ?';     $params[] = '%' . $search . '%'; }
$sql .= ' ORDER BY d.position, d.id';
$st = db()->prepare($sql); $st->execute($params);
$dishes = $st->fetchAll();

// Editing which dish?
$editing = null;
if (!empty($_GET['edit'])) {
    $s = db()->prepare('SELECT * FROM dishes WHERE id = ? AND restaurant_id = ?');
    $s->execute([(int)$_GET['edit'], $rid]);
    $editing = $s->fetch() ?: null;
}
$isNew = !empty($_GET['new']) || $editing;

$PAGE_TITLE = 'Plats';
$ACTIVE     = 'dishes';
$csrf       = csrf_token();
require __DIR__ . '/../shared/layout.php';
?>

<div class="page-head">
  <div>
    <h1>Plats</h1>
    <div class="sub">Ajoutez, modifiez ou retirez des plats de votre menu.</div>
  </div>
  <?php if (!$isNew): ?><a class="btn-primary" href="/admin/dishes.php?new=1">+ Nouveau plat</a><?php endif; ?>
</div>

<?php if ($isNew): ?>
<div class="card" style="padding: 24px; margin-bottom: 24px;">
  <h2 style="font-size:18px;font-weight:800;margin-bottom:16px;"><?= $editing ? 'Modifier le plat' : 'Nouveau plat' ?></h2>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int)($editing['id'] ?? 0) ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div><label class="label">Nom</label>
        <input class="input" name="name" required value="<?= e($editing['name'] ?? '') ?>" placeholder="Tajine agneau aux pruneaux"></div>
      <div><label class="label">Catégorie</label>
        <select class="select" name="category_id">
          <option value="">— Sans catégorie —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($editing && (int)$editing['category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select></div>

      <div style="grid-column: 1 / -1;"><label class="label">Description</label>
        <textarea class="textarea" name="description" rows="3" placeholder="Courte description appétissante."><?= e($editing['description'] ?? '') ?></textarea></div>

      <div><label class="label">Prix (<?= e($rest['currency']) ?>)</label>
        <input class="input" type="number" step="0.01" min="0" name="price" required value="<?= e(number_format((float)($editing['price'] ?? 0), 2, '.', '')) ?>"></div>

      <div><label class="label">Photo <?= $editing && $editing['photo'] ? '(laisser vide pour conserver)' : '' ?></label>
        <input class="input" type="file" name="photo" accept="image/*">
        <?php if ($editing && $editing['photo']): ?>
          <div style="margin-top:8px;"><img src="<?= e(UPLOADS_URL . '/' . $editing['photo']) ?>" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid var(--border);"></div>
        <?php endif; ?></div>

      <div style="grid-column: 1 / -1;display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:12px;background:var(--surface-2);border-radius:var(--radius-md);border:1px solid var(--border);">
        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
          <input type="checkbox" name="available" <?= (!$editing || (int)$editing['available'] === 1) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--gold);">
          <span><b>Disponible</b></span>
        </label>
        <span style="color:var(--border-strong);">·</span>
        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;">
          <input type="checkbox" name="is_special" <?= ($editing && (int)$editing['is_special'] === 1) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--gold);">
          <span><b>Menu du jour</b> — épinglé en haut du menu public</span>
        </label>
      </div>

      <div style="grid-column: 1 / -1;">
        <label class="label">Niveau d'épice</label>
        <div style="display:flex;gap:10px;">
          <?php for ($i = 0; $i <= 3; $i++):
            $checked = ($editing ? (int)$editing['spice'] : 0) === $i; ?>
            <label style="flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border:1px solid <?= $checked ? 'var(--accent)' : 'var(--border-strong)' ?>;border-radius:var(--radius-md);cursor:pointer;background:<?= $checked ? 'rgba(136,58,97,.06)' : 'var(--surface)' ?>;">
              <input type="radio" name="spice" value="<?= $i ?>" <?= $checked ? 'checked' : '' ?> style="accent-color:var(--accent);">
              <span style="font-weight:700;"><?= ['Aucun','Doux 🌶','Moyen 🌶🌶','Fort 🌶🌶🌶'][$i] ?></span>
            </label>
          <?php endfor; ?>
        </div>
      </div>

      <div style="grid-column: 1 / -1;">
        <label class="label">Allergènes &amp; régimes</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
          <?php
            $currFlags = $editing ? flags_to_list($editing['flags']) : [];
            foreach ($ALL_FLAGS as $f):
              [$lbl, $em] = flag_label($f); $on = in_array($f, $currFlags, true);
              $fname = 'flag_' . str_replace('-', '_', $f);
          ?>
            <label style="display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border:1px solid <?= $on ? 'var(--accent)' : 'var(--border-strong)' ?>;border-radius:999px;cursor:pointer;background:<?= $on ? 'rgba(136,58,97,.06)' : 'transparent' ?>;font-weight:700;font-size:13px;">
              <input type="checkbox" name="<?= e($fname) ?>" <?= $on ? 'checked' : '' ?> style="accent-color:var(--accent);">
              <?= e($em . ' ' . $lbl) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:20px;">
      <button class="btn-primary">Enregistrer</button>
      <a class="btn-ghost" href="/admin/dishes.php">Annuler</a>
    </div>
  </form>
</div>

<?php if ($editing):
  $gq = db()->prepare('SELECT * FROM dish_photos WHERE dish_id = ? ORDER BY position, id');
  $gq->execute([(int)$editing['id']]);
  $gphotos = $gq->fetchAll();
?>
<div class="card" style="padding: 24px; margin-bottom: 24px;">
  <h2 style="font-size:16px;font-weight:800;margin-bottom:8px;">Galerie photo <span style="color:var(--muted-ink);font-weight:600;font-size:13px;">(jusqu'à 4 photos)</span></h2>
  <p style="font-size:13px;color:var(--muted-ink);margin-bottom:16px;">Ces photos s'affichent dans un carrousel sur la page du plat. La photo principale reste celle enregistrée plus haut.</p>

  <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(140px, 1fr));gap:12px;margin-bottom:16px;">
    <?php foreach ($gphotos as $gp): ?>
      <div style="position:relative;border-radius:var(--radius-md);overflow:hidden;border:1px solid var(--border);aspect-ratio:1;">
        <img src="<?= e(UPLOADS_URL . '/' . $gp['photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
        <form method="post" style="position:absolute;top:6px;right:6px;" onsubmit="return confirm('Supprimer cette photo ?');">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="del_photo">
          <input type="hidden" name="photo_id" value="<?= (int)$gp['id'] ?>">
          <input type="hidden" name="dish_id" value="<?= (int)$editing['id'] ?>">
          <button class="icon-btn danger" title="Supprimer">✕</button>
        </form>
      </div>
    <?php endforeach; ?>

    <?php if (count($gphotos) < 4): ?>
      <form method="post" enctype="multipart/form-data" style="display:grid;place-items:center;border:2px dashed var(--border-strong);border-radius:var(--radius-md);aspect-ratio:1;background:var(--surface-2);cursor:pointer;" onclick="this.querySelector('input[type=file]').click();">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="add_photo">
        <input type="hidden" name="dish_id" value="<?= (int)$editing['id'] ?>">
        <input type="file" name="photo" accept="image/*" style="display:none;" onchange="this.form.submit();">
        <div style="text-align:center;color:var(--muted-ink);font-weight:700;font-size:13px;">+ Ajouter</div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<form method="get" class="toolbar">
  <input class="input" name="q" value="<?= e($search) ?>" placeholder="Rechercher un plat…">
  <select class="select" name="cat" style="max-width:220px;" onchange="this.form.submit()">
    <option value="0">Toutes les catégories</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= $filterCat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn-ghost">Filtrer</button>
  <?php if ($search || $filterCat): ?><a class="btn-ghost" href="/admin/dishes.php">Réinitialiser</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead><tr><th>Plat</th><th>Catégorie</th><th>Prix</th><th>État</th><th style="width:140px;text-align:right;">Actions</th></tr></thead>
    <tbody>
    <?php if (!$dishes): ?>
      <tr><td colspan="5" style="text-align:center;padding:40px 16px;color:var(--muted-ink);">Aucun plat. <a style="color:var(--accent);font-weight:700;" href="/admin/dishes.php?new=1">Ajouter un plat</a></td></tr>
    <?php else: foreach ($dishes as $d): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:12px;min-width:0;">
            <div class="thumb">
              <?php if ($d['photo']): ?><img src="<?= e(UPLOADS_URL . '/' . $d['photo']) ?>" alt=""><?php else: ?><span>★</span><?php endif; ?>
            </div>
            <div style="min-width:0;">
              <div style="font-weight:700;"><?= e($d['name']) ?></div>
              <?php if ($d['description']): ?><div style="color:var(--muted-ink);font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:360px;"><?= e($d['description']) ?></div><?php endif; ?>
            </div>
          </div>
        </td>
        <td><?= e($d['cat'] ?? '—') ?></td>
        <td class="price" style="font-size:14px;"><?= e(money((float)$d['price'])) ?></td>
        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button class="badge <?= $d['available'] ? 'badge-ok' : 'badge-bad' ?>" style="border:none;cursor:pointer;">
              <?= $d['available'] ? 'Disponible' : 'Épuisé' ?>
            </button>
          </form>
        </td>
        <td class="row-actions">
          <a class="icon-btn" href="/admin/dishes.php?edit=<?= (int)$d['id'] ?>" title="Modifier">✎</a>
          <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce plat ?');">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button class="icon-btn danger" title="Supprimer">✕</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../shared/layout_foot.php';

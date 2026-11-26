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
            $name_en = trim((string)($_POST['name_en'] ?? ''));
            $name_ar = trim((string)($_POST['name_ar'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));
            $desc_en = trim((string)($_POST['description_en'] ?? ''));
            $desc_ar = trim((string)($_POST['description_ar'] ?? ''));
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

            $optionsJson = trim((string)($_POST['options_json'] ?? ''));
            $options = null;
            if ($optionsJson !== '') {
                $decoded = json_decode($optionsJson, true);
                if (is_array($decoded)) {
                    $options = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                }
            }

            if ($id > 0) {
                if ($photo) {
                    db()->prepare('UPDATE dishes SET name=?, name_en=?, name_ar=?, description=?, description_en=?, description_ar=?, price=?, category_id=?, available=?, photo=?, flags=?, spice=?, is_special=?, options=? WHERE id=? AND restaurant_id=?')
                        ->execute([$name,$name_en,$name_ar,$desc,$desc_en,$desc_ar,$price,$cat,$avail,$photo,$flags,$spice,$special,$options,$id,$rid]);
                } else {
                    db()->prepare('UPDATE dishes SET name=?, name_en=?, name_ar=?, description=?, description_en=?, description_ar=?, price=?, category_id=?, available=?, flags=?, spice=?, is_special=?, options=? WHERE id=? AND restaurant_id=?')
                        ->execute([$name,$name_en,$name_ar,$desc,$desc_en,$desc_ar,$price,$cat,$avail,$flags,$spice,$special,$options,$id,$rid]);
                }
                header('Location: /admin/dishes.php?edit=' . $id . '&ok=' . rawurlencode('Plat mis à jour.'));
            } else {
                $pos = (int)(db()->query("SELECT COALESCE(MAX(position),-1)+1 FROM dishes WHERE restaurant_id = $rid")->fetchColumn());
                db()->prepare('INSERT INTO dishes (restaurant_id, category_id, name, name_en, name_ar, description, description_en, description_ar, price, photo, available, position, flags, spice, is_special, options) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$rid, $cat, $name, $name_en, $name_ar, $desc, $desc_en, $desc_ar, $price, $photo, $avail, $pos, $flags, $spice, $special, $options]);
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
      
      <!-- Multi-Language Card (Français, English, العربية) -->
      <div style="grid-column: 1 / -1; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 18px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px; border-bottom:1px solid var(--border); padding-bottom:12px;">
          <div>
            <span style="font-size:14px; font-weight:800; color:var(--text); display:block;">Langues du plat (Nom &amp; Description)</span>
            <span style="font-size:12px; color:var(--muted-ink);">Basculez entre les langues ou utilisez le bouton auto-traduire.</span>
          </div>

          <div style="display:flex; align-items:center; gap:8px;">
            <div style="display:inline-flex; background:var(--surface); border:1px solid var(--border-strong); border-radius:9999px; padding:3px; gap:3px;">
              <button type="button" class="lang-tab-pill active" id="tabBtn_fr" onclick="switchDishLang('fr')" style="padding:5px 12px; border-radius:9999px; border:none; font-size:12.5px; font-weight:700; cursor:pointer; background:var(--gold); color:#241017;">
                🇫🇷 Français <span style="font-size:10px; opacity:0.8;">(Principal)</span>
              </button>
              <button type="button" class="lang-tab-pill" id="tabBtn_en" onclick="switchDishLang('en')" style="padding:5px 12px; border-radius:9999px; border:none; font-size:12.5px; font-weight:700; cursor:pointer; background:transparent; color:var(--muted-ink);">
                🇬🇧 English <span id="badge_en_ok" style="color:var(--green-2); font-weight:800;"><?= !empty($editing['name_en']) ? '<i class="fa-solid fa-circle-check"></i>' : '' ?></span>
              </button>
              <button type="button" class="lang-tab-pill" id="tabBtn_ar" onclick="switchDishLang('ar')" style="padding:5px 12px; border-radius:9999px; border:none; font-size:12.5px; font-weight:700; cursor:pointer; background:transparent; color:var(--muted-ink);">
                🇲🇦 العربية <span id="badge_ar_ok" style="color:var(--green-2); font-weight:800;"><?= !empty($editing['name_ar']) ? '<i class="fa-solid fa-circle-check"></i>' : '' ?></span>
              </button>
            </div>

            <button type="button" class="btn-ghost" style="font-size:12px; padding:6px 12px; border-radius:9999px; background:var(--surface); display:inline-flex; align-items:center; gap:5px; font-weight:700;" onclick="autoTranslateDish()" title="Générer automatiquement l'anglais et l'arabe à partir du français">
              <span><i class="fa-solid fa-wand-magic-sparkles"></i> Auto-traduire</span>
            </button>
          </div>
        </div>

        <!-- French Tab Pane -->
        <div class="dish-lang-pane" id="pane_fr">
          <div style="margin-bottom:12px;">
            <label class="label">Nom du plat (Français) <span style="color:var(--accent);">*</span></label>
            <input class="input" id="dish_name_fr" name="name" required value="<?= e($editing['name'] ?? '') ?>" placeholder="ex: Burger Sola">
          </div>
          <div>
            <label class="label">Description (Français)</label>
            <textarea class="textarea" id="dish_desc_fr" name="description" rows="2" placeholder="ex: Préparé avec soin et des ingrédients frais du jour."><?= e($editing['description'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- English Tab Pane -->
        <div class="dish-lang-pane" id="pane_en" style="display:none;">
          <div style="margin-bottom:12px;">
            <label class="label">Dish Name (English) <span style="font-weight:400; color:var(--muted-ink);">(Optionnel - utilise le français si vide)</span></label>
            <input class="input" id="dish_name_en" name="name_en" value="<?= e($editing['name_en'] ?? '') ?>" placeholder="e.g. Sola Signature Burger">
          </div>
          <div>
            <label class="label">Description (English)</label>
            <textarea class="textarea" id="dish_desc_en" name="description_en" rows="2" placeholder="e.g. Prepared with fresh daily ingredients and secret house sauce."><?= e($editing['description_en'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Arabic Tab Pane -->
        <div class="dish-lang-pane" id="pane_ar" style="display:none;" dir="rtl">
          <div style="margin-bottom:12px;">
            <label class="label" style="text-align:right;">اسم الطبق (بالعربية) <span style="font-weight:400; color:var(--muted-ink);">(اختياري - يظهر بالفرنسية إذا تُرِك فارغاً)</span></label>
            <input class="input" id="dish_name_ar" name="name_ar" value="<?= e($editing['name_ar'] ?? '') ?>" placeholder="مثال: برغر سولا الخاص" style="text-align:right; font-family:sans-serif;">
          </div>
          <div>
            <label class="label" style="text-align:right;">وصف الطبق (بالعربية)</label>
            <textarea class="textarea" id="dish_desc_ar" name="description_ar" rows="2" placeholder="مثال: محضر بعناية بمكونات طازجة يومياً وصلصة الدار اللذيذة." style="text-align:right; font-family:sans-serif;"><?= e($editing['description_ar'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <div><label class="label">Catégorie</label>
        <select class="select" name="category_id">
          <option value="">— Sans catégorie —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($editing && (int)$editing['category_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select></div>

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
              <span style="font-weight:700;">
                <?= ['Aucun','Doux','Moyen','Fort'][$i] ?>
                <?php for ($p = 0; $p < $i; $p++): ?><i class="fa-solid fa-pepper-hot" style="color:#C62828;font-size:12px;"></i><?php endfor; ?>
              </span>
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
              <?= $em ?> <?= e($lbl) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Options & Caractéristiques Builder -->
      <div style="grid-column: 1 / -1; margin-top: 10px; border-top: 1px solid var(--border); padding-top: 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; flex-wrap:wrap; gap:10px;">
          <div>
            <label class="label" style="font-size:16px; font-weight:800; margin-bottom:4px; display:block;">Options & Caractéristiques (Sauces, Cuisson, Suppléments...)</label>
            <p style="font-size:12.5px; color:var(--muted-ink); margin:0;">Ces options s'affichent lorsque le client clique sur <b>"Ajouter au panier"</b>. Laissez vide si le plat n'a aucune option.</p>
          </div>
          <button type="button" class="btn-ghost" style="font-size:13px; padding:6px 14px;" onclick="addOptionGroup()">+ Nouveau groupe d'options</button>
        </div>

        <div id="optionsBuilderContainer" style="display:flex; flex-direction:column; gap:16px;">
          <!-- Dynamically generated groups -->
        </div>

        <input type="hidden" name="options_json" id="optionsJsonInput" value="<?= e($editing['options'] ?? '') ?>">
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
          <button class="icon-btn danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
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
              <?php if ($d['photo']): ?><img src="<?= e(UPLOADS_URL . '/' . $d['photo']) ?>" alt=""><?php else: ?><i class="fa-solid fa-utensils" style="color:var(--muted-ink);"></i><?php endif; ?>
            </div>
            <div style="min-width:0;">
              <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                <span style="font-weight:700;"><?= e($d['name']) ?></span>
                <span style="display:inline-flex;gap:3px;font-size:11px;background:var(--surface-2);padding:1px 6px;border-radius:12px;border:1px solid var(--border);">
                  <span title="Français">🇫🇷</span>
                  <?php if (!empty($d['name_en'])): ?><span title="English : <?= e($d['name_en']) ?>">🇬🇧</span><?php endif; ?>
                  <?php if (!empty($d['name_ar'])): ?><span title="العربية : <?= e($d['name_ar']) ?>">🇲🇦</span><?php endif; ?>
                </span>
              </div>
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
          <a class="icon-btn" href="/admin/dishes.php?edit=<?= (int)$d['id'] ?>" title="Modifier"><i class="fa-solid fa-pen"></i></a>
          <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce plat ?');">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button class="icon-btn danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php if ($isNew): ?>
<script>
// Tab switcher for Dish Main Info
function switchDishLang(lang) {
  ['fr', 'en', 'ar'].forEach(l => {
    const pane = document.getElementById('pane_' + l);
    const btn = document.getElementById('tabBtn_' + l);
    if (pane) pane.style.display = (l === lang ? 'block' : 'none');
    if (btn) {
      if (l === lang) {
        btn.style.background = 'var(--gold)';
        btn.style.color = '#241017';
      } else {
        btn.style.background = 'transparent';
        btn.style.color = 'var(--muted-ink)';
      }
    }
  });
}

// Food vocabulary dictionary for instant smart translation
const TRANSLATION_DICT = {
  // Common dish prefixes & types
  "burger": { en: "Burger", ar: "برغر" },
  "pizza": { en: "Pizza", ar: "بيتزا" },
  "pâtes": { en: "Pasta", ar: "معكرونة" },
  "pates": { en: "Pasta", ar: "معكرونة" },
  "spaghetti": { en: "Spaghetti", ar: "سباغيتي" },
  "penne": { en: "Penne", ar: "بيني" },
  "salade": { en: "Salad", ar: "سلطة" },
  "tajine": { en: "Tagine", ar: "طاجين" },
  "sandwich": { en: "Sandwich", ar: "ساندويتش" },
  "tacos": { en: "Tacos", ar: "تاكوس" },
  "tiramisu": { en: "Tiramisu", ar: "تيراميسو" },
  "fondant": { en: "Lava Cake", ar: "فوندان" },
  "chocolat": { en: "Chocolate", ar: "الشوكولاتة" },
  "limonade": { en: "Lemonade", ar: "ليموناضة" },
  "thé": { en: "Tea", ar: "شاي" },
  "the": { en: "Tea", ar: "شاي" },
  "café": { en: "Coffee", ar: "قهوة" },
  "cafe": { en: "Coffee", ar: "قهوة" },
  "jus": { en: "Juice", ar: "عصير" },
  "eau": { en: "Water", ar: "ماء" },
  "mozzarella": { en: "Mozzarella", ar: "موزاريلا" },
  "burrata": { en: "Burrata", ar: "بوراتا" },
  "poulet": { en: "Chicken", ar: "دجاج" },
  "boeuf": { en: "Beef", ar: "لحم بقري" },
  "bœuf": { en: "Beef", ar: "لحم بقري" },
  "viande": { en: "Meat", ar: "لحم" },
  "poisson": { en: "Fish", ar: "سمك" },
  "crevettes": { en: "Shrimp", ar: "جمبري" },
  "sauce": { en: "Sauce", ar: "صلصة" },
  "fromage": { en: "Cheese", ar: "جبن" },
  "truffe": { en: "Truffle", ar: "كمأة (تروف)" },
  "maison": { en: "Homemade", ar: "الدار" },
  "classic": { en: "Classic", ar: "كلاسيك" },
  "frites": { en: "Fries", ar: "بطاطس مقلية" },
  "douce": { en: "Sweet", ar: "حلوة" },
  "piquante": { en: "Spicy", ar: "حارة" },
  "barbecue": { en: "BBQ", ar: "باربيكيو" },
  "cuisson": { en: "Cooking", ar: "درجة الطهي" },
  "suppléments": { en: "Extras", ar: "إضافات" }
};

function autoTranslateDish() {
  const nameFr = (document.getElementById('dish_name_fr').value || '').trim();
  const descFr = (document.getElementById('dish_desc_fr').value || '').trim();

  if (!nameFr) {
    alert('Veuillez d\'abord saisir le nom du plat en français.');
    return;
  }

  // Translate Name
  let nameEn = nameFr;
  let nameAr = nameFr;
  
  // Replace known terms
  for (const [fr, tr] of Object.entries(TRANSLATION_DICT)) {
    const regex = new RegExp('\\b' + fr + '\\b', 'gi');
    if (regex.test(nameFr)) {
      nameEn = nameEn.replace(regex, tr.en);
      nameAr = nameAr.replace(regex, tr.ar);
    }
  }

  // Clean descriptions
  let descEn = descFr;
  let descAr = descFr;
  if (descFr) {
    for (const [fr, tr] of Object.entries(TRANSLATION_DICT)) {
      const regex = new RegExp('\\b' + fr + '\\b', 'gi');
      descEn = descEn.replace(regex, tr.en);
      descAr = descAr.replace(regex, tr.ar);
    }
  }

  if (!document.getElementById('dish_name_en').value.trim()) {
    document.getElementById('dish_name_en').value = nameEn;
  }
  if (descFr && !document.getElementById('dish_desc_en').value.trim()) {
    document.getElementById('dish_desc_en').value = descEn;
  }

  if (!document.getElementById('dish_name_ar').value.trim()) {
    document.getElementById('dish_name_ar').value = nameAr;
  }
  if (descFr && !document.getElementById('dish_desc_ar').value.trim()) {
    document.getElementById('dish_desc_ar').value = descAr;
  }

  document.getElementById('badge_en_ok').innerHTML = '<i class="fa-solid fa-circle-check"></i>';
  document.getElementById('badge_ar_ok').innerHTML = '<i class="fa-solid fa-circle-check"></i>';

  alert('✨ Traductions générées avec succès ! Vous pouvez basculer sur les onglets English et العربية pour vérifier ou ajuster.');
}

let currentOptions = <?= !empty($editing['options']) ? $editing['options'] : '[]' ?>;
if (!Array.isArray(currentOptions)) currentOptions = [];

let openLangGroupIdx = null;

function toggleGroupLang(gIdx) {
  openLangGroupIdx = (openLangGroupIdx === gIdx ? null : gIdx);
  renderOptionsBuilder();
}

function renderOptionsBuilder() {
  const container = document.getElementById('optionsBuilderContainer');
  if (!container) return;

  if (currentOptions.length === 0) {
    container.innerHTML = `
      <div style="padding:18px; text-align:center; background:var(--surface-2); border-radius:var(--radius-md); border:1px dashed var(--border-strong); color:var(--muted-ink); font-size:13px;">
        Aucune option configurée pour ce plat. Le plat sera ajouté directement au panier.<br>
        <button type="button" class="btn-ghost" style="margin-top:10px; font-size:12.5px;" onclick="addOptionGroup()">+ Ajouter des options (ex: Sauces, Cuisson, Suppléments)</button>
      </div>
    `;
    syncOptionsJson();
    return;
  }

  container.innerHTML = currentOptions.map((grp, gIdx) => {
    const isLangOpen = (openLangGroupIdx === gIdx);
    return `
    <div style="background:var(--surface); border:1px solid var(--border-strong); border-radius:var(--radius-md); padding:16px; position:relative; box-shadow:0 1px 4px rgba(0,0,0,0.04);">
      <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
        <div style="flex:1; display:grid; grid-template-columns: 2fr 1fr; gap:12px; min-width:240px;">
          <div>
            <label style="font-size:12px; font-weight:700; color:var(--muted-ink); display:block; margin-bottom:4px;">Nom du groupe (Français)</label>
            <input type="text" class="input" value="${escapeHtml(grp.name || '')}" oninput="updateGroupName(${gIdx}, this.value)" placeholder="ex: Choix de Sauce, Suppléments">
            <button type="button" class="btn-ghost" style="font-size:11.5px; padding:3px 10px; margin-top:6px; border-radius:999px;" onclick="toggleGroupLang(${gIdx})">
              <i class="fa-solid fa-globe"></i> ${isLangOpen ? 'Masquer traductions' : 'Traductions (English / العربية)'} ${grp.name_en || grp.name_ar ? '<i class="fa-solid fa-check" style="color:var(--green-2)"></i>' : ''}
            </button>
          </div>
          <div>
            <label style="font-size:12px; font-weight:700; color:var(--muted-ink); display:block; margin-bottom:4px;">Type de sélection</label>
            <select class="select" onchange="updateGroupType(${gIdx}, this.value)">
              <option value="single" ${grp.type === 'single' ? 'selected' : ''}>1 seul choix obligatoire (Radio)</option>
              <option value="multiple" ${grp.type === 'multiple' ? 'selected' : ''}>Choix multiples optionnels (Cases)</option>
            </select>
          </div>
        </div>
        <button type="button" class="icon-btn danger" style="margin-top:20px;" title="Supprimer ce groupe" onclick="removeOptionGroup(${gIdx})"><i class="fa-solid fa-trash"></i></button>
      </div>

      <!-- Expanded Language Row for Group Title -->
      ${isLangOpen ? `
        <div style="background:var(--surface-2); border:1px solid var(--border); border-radius:8px; padding:10px 14px; margin-bottom:14px; display:grid; grid-template-columns:1fr 1fr; gap:10px;">
          <div>
            <label style="font-size:11.5px; font-weight:700; color:var(--muted-ink); display:block; margin-bottom:3px;">🇬🇧 Titre en Anglais</label>
            <input type="text" class="input" style="font-size:13px; padding:6px 10px;" value="${escapeHtml(grp.name_en || '')}" oninput="updateGroupNameEn(${gIdx}, this.value)" placeholder="e.g. Sauce Choice">
          </div>
          <div dir="rtl">
            <label style="font-size:11.5px; font-weight:700; color:var(--muted-ink); display:block; margin-bottom:3px; text-align:right;">🇲🇦 العنوان بالعربية</label>
            <input type="text" class="input" style="font-size:13px; padding:6px 10px; text-align:right;" value="${escapeHtml(grp.name_ar || '')}" oninput="updateGroupNameAr(${gIdx}, this.value)" placeholder="مثال: اختيار الصلصة">
          </div>
        </div>
      ` : ''}

      <div style="margin-top:10px;">
        <label style="font-size:12px; font-weight:700; color:var(--muted-ink); display:block; margin-bottom:8px;">Choix disponibles &amp; Prix du supplément :</label>
        <div style="display:flex; flex-direction:column; gap:8px;">
          ${(grp.items || []).map((it, iIdx) => `
            <div>
              <div style="display:flex; align-items:center; gap:8px;">
                <input type="text" class="input" style="flex:2;" value="${escapeHtml(it.name || '')}" oninput="updateItemName(${gIdx}, ${iIdx}, this.value)" placeholder="Nom du choix (ex: Sauce Algérienne 🌶️)">
                <div style="display:flex; align-items:center; gap:4px; flex:1; max-width:140px;">
                  <input type="number" class="input" style="width:100%;" min="0" step="0.5" value="${Number(it.price || 0)}" oninput="updateItemPrice(${gIdx}, ${iIdx}, this.value)" placeholder="0">
                  <span style="font-size:12px; font-weight:700; color:var(--muted-ink);"><?= e($rest['currency']) ?></span>
                </div>
                <button type="button" class="icon-btn danger" style="width:32px; height:32px;" title="Supprimer ce choix" onclick="removeItem(${gIdx}, ${iIdx})"><i class="fa-solid fa-xmark"></i></button>
              </div>

              <!-- Translated Choice Subfields (Visible when group translation is open) -->
              ${isLangOpen ? `
                <div style="display:flex; gap:8px; margin-top:4px; padding-left:10px; border-left:2px solid var(--gold);">
                  <input type="text" class="input" style="flex:1; font-size:12px; padding:4px 8px;" value="${escapeHtml(it.name_en || '')}" oninput="updateItemNameEn(${gIdx}, ${iIdx}, this.value)" placeholder="🇬🇧 English name (e.g. Algerian Sauce)">
                  <input type="text" class="input" style="flex:1; font-size:12px; padding:4px 8px; text-align:right;" dir="rtl" value="${escapeHtml(it.name_ar || '')}" oninput="updateItemNameAr(${gIdx}, ${iIdx}, this.value)" placeholder="🇲🇦 الاسم بالعربية (مثال: صلصة جزائرية)">
                </div>
              ` : ''}
            </div>
          `).join('')}
        </div>
        <button type="button" class="btn-ghost" style="margin-top:10px; font-size:12px; padding:4px 10px;" onclick="addItem(${gIdx})">+ Ajouter un choix</button>
      </div>
    </div>
  `;
  }).join('');

  syncOptionsJson();
}

function escapeHtml(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function syncOptionsJson() {
  const input = document.getElementById('optionsJsonInput');
  if (input) {
    input.value = currentOptions.length > 0 ? JSON.stringify(currentOptions) : '';
  }
}

function addOptionGroup() {
  currentOptions.push({
    id: 'grp_' + Date.now(),
    name: 'Nouveau groupe',
    name_en: '',
    name_ar: '',
    type: 'single',
    required: true,
    items: [
      { name: 'Choix 1', name_en: '', name_ar: '', price: 0 },
      { name: 'Choix 2', name_en: '', name_ar: '', price: 0 }
    ]
  });
  renderOptionsBuilder();
}

function removeOptionGroup(gIdx) {
  if (confirm('Supprimer ce groupe d\'options ?')) {
    currentOptions.splice(gIdx, 1);
    renderOptionsBuilder();
  }
}

function updateGroupName(gIdx, val) {
  currentOptions[gIdx].name = val;
  syncOptionsJson();
}

function updateGroupNameEn(gIdx, val) {
  currentOptions[gIdx].name_en = val;
  syncOptionsJson();
}

function updateGroupNameAr(gIdx, val) {
  currentOptions[gIdx].name_ar = val;
  syncOptionsJson();
}

function updateGroupType(gIdx, val) {
  currentOptions[gIdx].type = val;
  currentOptions[gIdx].required = (val === 'single');
  syncOptionsJson();
}

function addItem(gIdx) {
  if (!currentOptions[gIdx].items) currentOptions[gIdx].items = [];
  currentOptions[gIdx].items.push({ name: '', name_en: '', name_ar: '', price: 0 });
  renderOptionsBuilder();
}

function removeItem(gIdx, iIdx) {
  currentOptions[gIdx].items.splice(iIdx, 1);
  renderOptionsBuilder();
}

function updateItemName(gIdx, iIdx, val) {
  currentOptions[gIdx].items[iIdx].name = val;
  syncOptionsJson();
}

function updateItemNameEn(gIdx, iIdx, val) {
  currentOptions[gIdx].items[iIdx].name_en = val;
  syncOptionsJson();
}

function updateItemNameAr(gIdx, iIdx, val) {
  currentOptions[gIdx].items[iIdx].name_ar = val;
  syncOptionsJson();
}

function updateItemPrice(gIdx, iIdx, val) {
  currentOptions[gIdx].items[iIdx].price = parseFloat(val) || 0;
  syncOptionsJson();
}

document.addEventListener('DOMContentLoaded', () => {
  renderOptionsBuilder();
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../shared/layout_foot.php';

<?php
require __DIR__ . '/../shared/config.php';
$user = require_auth();
$rest = current_restaurant($user);
$rid  = (int)$rest['id'];

// Ensure image column exists (safe on both SQLite and Postgres)
try { db()->exec("ALTER TABLE categories ADD COLUMN image TEXT DEFAULT ''"); } catch (Throwable $e) {}

function save_cat_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $info = getimagesize($file['tmp_name']);
    if (!$info) throw new RuntimeException('Fichier image invalide.');
    $extMap = [IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_WEBP => '.webp', IMAGETYPE_GIF => '.gif'];
    if (!isset($extMap[$info[2]])) throw new RuntimeException('Format non supporté (jpg/png/webp/gif).');
    if ($file['size'] > 6 * 1024 * 1024) throw new RuntimeException('Image trop lourde (max 6 Mo).');
    $name = 'cat_' . bin2hex(random_bytes(8)) . $extMap[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . DIRECTORY_SEPARATOR . $name)) {
        throw new RuntimeException('Échec de téléchargement.');
    }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $name_en = trim((string)($_POST['name_en'] ?? ''));
            $name_ar = trim((string)($_POST['name_ar'] ?? ''));
            if ($name === '') throw new RuntimeException('Nom requis');
            $pos = (int)(db()->query("SELECT COALESCE(MAX(position),-1)+1 FROM categories WHERE restaurant_id = $rid")->fetchColumn());
            $st = db()->prepare('INSERT INTO categories (restaurant_id, name, name_en, name_ar, position) VALUES (?,?,?,?,?)');
            $st->execute([$rid, $name, $name_en, $name_ar, $pos]);
            header('Location: /admin/categories.php?ok=' . rawurlencode('Catégorie ajoutée.'));
            exit;
        }
        if ($action === 'rename') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $name_en = trim((string)($_POST['name_en'] ?? ''));
            $name_ar = trim((string)($_POST['name_ar'] ?? ''));
            if ($id <= 0 || $name === '') throw new RuntimeException('Données invalides');
            $st = db()->prepare('UPDATE categories SET name = ?, name_en = ?, name_ar = ? WHERE id = ? AND restaurant_id = ?');
            $st->execute([$name, $name_en, $name_ar, $id, $rid]);
            header('Location: /admin/categories.php?ok=' . rawurlencode('Catégorie mise à jour.'));
            exit;
        }
        if ($action === 'upload_image') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('ID invalide');
            $fname = save_cat_image($_FILES['image'] ?? []);
            if (!$fname) throw new RuntimeException('Aucune image fournie.');
            $st = db()->prepare('UPDATE categories SET image = ? WHERE id = ? AND restaurant_id = ?');
            $st->execute([$fname, $id, $rid]);
            header('Location: /admin/categories.php?ok=' . rawurlencode('Image mise à jour.'));
            exit;
        }
        if ($action === 'clear_image') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('ID invalide');
            $st = db()->prepare("UPDATE categories SET image = '' WHERE id = ? AND restaurant_id = ?");
            $st->execute([$id, $rid]);
            header('Location: /admin/categories.php?ok=' . rawurlencode('Image retirée.'));
            exit;
        }
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('ID invalide');
            $st = db()->prepare('DELETE FROM categories WHERE id = ? AND restaurant_id = ?');
            $st->execute([$id, $rid]);
            header('Location: /admin/categories.php?ok=' . rawurlencode('Catégorie supprimée.'));
            exit;
        }
        if ($action === 'move') {
            $id  = (int)($_POST['id'] ?? 0);
            $dir = $_POST['dir'] ?? 'up';
            $cur = db()->prepare('SELECT position FROM categories WHERE id = ? AND restaurant_id = ?');
            $cur->execute([$id, $rid]);
            $curPos = $cur->fetchColumn();
            if ($curPos === false) throw new RuntimeException('Introuvable');
            $op = $dir === 'up' ? '<' : '>';
            $ord = $dir === 'up' ? 'DESC' : 'ASC';
            $nb = db()->prepare("SELECT id, position FROM categories WHERE restaurant_id = ? AND position $op ? ORDER BY position $ord LIMIT 1");
            $nb->execute([$rid, (int)$curPos]);
            $other = $nb->fetch();
            if ($other) {
                db()->beginTransaction();
                db()->prepare('UPDATE categories SET position = ? WHERE id = ?')->execute([(int)$other['position'], $id]);
                db()->prepare('UPDATE categories SET position = ? WHERE id = ?')->execute([(int)$curPos, (int)$other['id']]);
                db()->commit();
            }
            header('Location: /admin/categories.php');
            exit;
        }
    } catch (Throwable $e) {
        header('Location: /admin/categories.php?err=' . rawurlencode($e->getMessage()));
        exit;
    }
}

$st = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM dishes d WHERE d.category_id = c.id) AS dish_count FROM categories c WHERE c.restaurant_id = ? ORDER BY c.position, c.id');
$st->execute([$rid]);
$cats = $st->fetchAll();

$PAGE_TITLE = 'Catégories';
$ACTIVE     = 'categories';
$csrf       = csrf_token();
require __DIR__ . '/../shared/layout.php';
?>

<div class="page-head">
  <div>
    <h1>Catégories</h1>
    <div class="sub">Organisez les sections de votre menu.</div>
  </div>
</div>

<div class="card" style="padding: 20px; margin-bottom: 20px;">
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="create">
    <div style="font-size:14px; font-weight:800; margin-bottom:12px;">+ Ajouter une nouvelle catégorie</div>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap:12px; align-items:flex-end;">
      <div>
        <label class="label">🇫🇷 Nom (Français) *</label>
        <input class="input" name="name" required placeholder="Ex: Burgers, Salades…">
      </div>
      <div>
        <label class="label">🇬🇧 Name (English)</label>
        <input class="input" name="name_en" placeholder="e.g. Burgers, Salads…">
      </div>
      <div dir="rtl">
        <label class="label" style="text-align:right;">🇲🇦 الاسم (العربية)</label>
        <input class="input" name="name_ar" placeholder="مثال: برغر، سلطات…" style="text-align:right; font-family:sans-serif;">
      </div>
      <button class="btn-primary" style="height:42px; padding:0 22px;">+ Ajouter</button>
    </div>
  </form>
</div>

<?php if (!empty($_GET['ok'])): ?>
  <div class="card" style="padding:10px 14px;margin-bottom:14px;border-left:4px solid var(--green-2);color:var(--green-2);font-weight:700;"><?= e($_GET['ok']) ?></div>
<?php endif; if (!empty($_GET['err'])): ?>
  <div class="card" style="padding:10px 14px;margin-bottom:14px;border-left:4px solid var(--bad-ink);color:var(--bad-ink);font-weight:700;"><?= e($_GET['err']) ?></div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead><tr><th style="width:90px;">Image</th><th style="width:50px;">#</th><th>🇫🇷 Français</th><th>🇬🇧 English</th><th>🇲🇦 العربية</th><th style="width:80px;">Plats</th><th style="width:200px;text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php if (!$cats): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px 16px;color:var(--muted-ink);">Aucune catégorie.</td></tr>
      <?php else: foreach ($cats as $i => $c): $img = trim((string)($c['image'] ?? '')); ?>
        <tr>
          <td>
            <div style="display:flex;flex-direction:column;align-items:center;gap:6px;">
              <div style="width:64px;height:64px;border-radius:12px;overflow:hidden;background:var(--surface-2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;">
                <?php if ($img): ?>
                  <img src="<?= e(UPLOADS_URL . '/' . $img) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                  <i class="fa-solid fa-image" style="color:var(--muted-ink);font-size:20px;"></i>
                <?php endif; ?>
              </div>
              <div style="display:flex;flex-direction:column;gap:4px;align-items:center;">
                <form method="post" enctype="multipart/form-data">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="action" value="upload_image">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <label class="btn-ghost" style="padding:4px 10px;font-size:11.5px;cursor:pointer;white-space:nowrap;">
                    <i class="fa-solid fa-upload"></i> Changer
                    <input type="file" name="image" accept="image/*" style="display:none;" onchange="this.form.submit()">
                  </label>
                </form>
                <?php if ($img): ?>
                  <form method="post" onsubmit="return confirm('Retirer l\'image ?');">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="clear_image">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="icon-btn danger" title="Retirer l'image" style="padding:2px 8px;"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td style="color:var(--muted-ink);font-weight:700;"><?= $i + 1 ?></td>
          <td colspan="3" style="padding:6px 12px;">
            <form method="post" id="cat_form_<?= (int)$c['id'] ?>" style="display:grid; grid-template-columns: 1fr 1fr 1fr auto; gap:8px; align-items:center;">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input class="input" name="name" value="<?= e($c['name']) ?>" title="Nom en français" placeholder="Nom FR" style="font-size:13px; padding:6px 10px;">
              <input class="input" name="name_en" value="<?= e($c['name_en'] ?? '') ?>" title="Nom en anglais" placeholder="Name EN" style="font-size:13px; padding:6px 10px;">
              <input class="input" name="name_ar" value="<?= e($c['name_ar'] ?? '') ?>" title="الاسم بالعربية" placeholder="الاسم بالعربية" dir="rtl" style="font-size:13px; padding:6px 10px; text-align:right;">
              <button class="btn-ghost" style="padding:6px 12px; font-size:12.5px; white-space:nowrap;">Enregistrer</button>
            </form>
          </td>
          <td><span class="badge badge-gold"><?= (int)$c['dish_count'] ?></span></td>
          <td class="row-actions">
            <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="dir" value="up"><button class="icon-btn" title="Monter" <?= $i === 0 ? 'disabled style="opacity:.3;"' : '' ?>><i class="fa-solid fa-chevron-up"></i></button></form>
            <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="dir" value="down"><button class="icon-btn" title="Descendre" <?= $i === count($cats) - 1 ? 'disabled style="opacity:.3;"' : '' ?>><i class="fa-solid fa-chevron-down"></i></button></form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette catégorie ? Les plats associés resteront mais sans catégorie.');">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="icon-btn danger" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../shared/layout_foot.php';

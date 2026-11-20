<?php
require __DIR__ . '/../shared/config.php';
$user = require_auth();
$rest = current_restaurant($user);
$rid  = (int)$rest['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') throw new RuntimeException('Nom requis');
            $pos = (int)(db()->query("SELECT COALESCE(MAX(position),-1)+1 FROM categories WHERE restaurant_id = $rid")->fetchColumn());
            $st = db()->prepare('INSERT INTO categories (restaurant_id, name, position) VALUES (?,?,?)');
            $st->execute([$rid, $name, $pos]);
            header('Location: /admin/categories.php?ok=' . rawurlencode('Catégorie ajoutée.'));
            exit;
        }
        if ($action === 'rename') {
            $id = (int)($_POST['id'] ?? 0); $name = trim((string)($_POST['name'] ?? ''));
            if ($id <= 0 || $name === '') throw new RuntimeException('Données invalides');
            $st = db()->prepare('UPDATE categories SET name = ? WHERE id = ? AND restaurant_id = ?');
            $st->execute([$name, $id, $rid]);
            header('Location: /admin/categories.php?ok=' . rawurlencode('Catégorie renommée.'));
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

<div class="card" style="padding: 18px; margin-bottom: 20px;">
  <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="create">
    <div style="flex:1;min-width:220px;">
      <label class="label">Nouvelle catégorie</label>
      <input class="input" name="name" required placeholder="Ex: Entrées, Pizzas, Desserts…">
    </div>
    <button class="btn-primary">+ Ajouter</button>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th style="width:60px;">Ordre</th><th>Nom</th><th>Plats</th><th style="width:200px;text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php if (!$cats): ?>
        <tr><td colspan="4" style="text-align:center;padding:40px 16px;color:var(--muted-ink);">Aucune catégorie.</td></tr>
      <?php else: foreach ($cats as $i => $c): ?>
        <tr>
          <td style="color:var(--muted-ink);font-weight:700;"><?= $i + 1 ?></td>
          <td>
            <form method="post" style="display:flex;gap:8px;align-items:center;">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input class="input" name="name" value="<?= e($c['name']) ?>" style="max-width:280px;">
              <button class="btn-ghost" style="padding:8px 14px;">Enregistrer</button>
            </form>
          </td>
          <td><span class="badge badge-gold"><?= (int)$c['dish_count'] ?> plats</span></td>
          <td class="row-actions">
            <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="dir" value="up"><button class="icon-btn" title="Monter" <?= $i === 0 ? 'disabled style="opacity:.3;"' : '' ?>>▲</button></form>
            <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="dir" value="down"><button class="icon-btn" title="Descendre" <?= $i === count($cats) - 1 ? 'disabled style="opacity:.3;"' : '' ?>>▼</button></form>
            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette catégorie ? Les plats associés resteront mais sans catégorie.');">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="icon-btn danger" title="Supprimer">✕</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../shared/layout_foot.php';

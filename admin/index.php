<?php
require __DIR__ . '/../shared/config.php';
$user = require_auth();
$rest = current_restaurant($user);

$catCount   = (int)(db()->query('SELECT COUNT(*) FROM categories WHERE restaurant_id = ' . (int)$rest['id'])->fetchColumn());
$dishCount  = (int)(db()->query('SELECT COUNT(*) FROM dishes     WHERE restaurant_id = ' . (int)$rest['id'])->fetchColumn());
$availCount = (int)(db()->query('SELECT COUNT(*) FROM dishes     WHERE restaurant_id = ' . (int)$rest['id'] . ' AND available = 1')->fetchColumn());
$outCount   = $dishCount - $availCount;

$recent = db()->prepare('SELECT d.*, c.name AS cat FROM dishes d LEFT JOIN categories c ON c.id = d.category_id WHERE d.restaurant_id = ? ORDER BY d.id DESC LIMIT 6');
$recent->execute([$rest['id']]);
$recent = $recent->fetchAll();

try {
  $todayResv = db()->prepare("SELECT * FROM reservations WHERE restaurant_id = ? AND date = ? ORDER BY time");
  $todayResv->execute([$rest['id'], date('Y-m-d')]);
  $todayResv = $todayResv->fetchAll();
  $pendingResv = (int)(db()->query("SELECT COUNT(*) FROM reservations WHERE restaurant_id = " . (int)$rest['id'] . " AND status = 'pending'")->fetchColumn());
  $activePromo = (int)(db()->query("SELECT COUNT(*) FROM promotions WHERE restaurant_id = " . (int)$rest['id'] . " AND active = 1")->fetchColumn());
  
  $recentOrders = db()->prepare("SELECT * FROM orders WHERE restaurant_id = ? ORDER BY id DESC LIMIT 5");
  $recentOrders->execute([$rest['id']]);
  $recentOrders = $recentOrders->fetchAll();
  $pendingOrders = (int)(db()->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = " . (int)$rest['id'] . " AND status = 'pending'")->fetchColumn());
  $todayOrdersCount = (int)(db()->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = " . (int)$rest['id'])->fetchColumn());
} catch (Throwable $e) {
  $todayResv = []; $pendingResv = 0; $activePromo = 0;
  $recentOrders = []; $pendingOrders = 0; $todayOrdersCount = 0;
}

$PAGE_TITLE = 'Tableau de bord';
$ACTIVE     = 'dashboard';
require __DIR__ . '/../shared/layout.php';
?>

<div class="page-head">
  <div>
    <h1>Bonjour <?= e(explode(' ', $user['display_name'] ?: 'Admin')[0]) ?> <span class="star">★</span></h1>
    <div class="sub">Voici l'état de votre menu digital.</div>
  </div>
  <a class="btn-primary" href="/admin/dishes.php?new=1">+ Ajouter un plat</a>
</div>

<div class="stat-row">
  <div class="stat">
    <div class="lbl">Commandes en direct</div>
    <div class="val" style="color:var(--accent);"><?= $todayOrdersCount ?></div>
    <div class="delta badge <?= $pendingOrders > 0 ? 'badge-bad' : 'badge-ok' ?>"><?= $pendingOrders ?> en attente</div>
  </div>
  <div class="stat"><div class="lbl">Plats au total</div><div class="val"><?= $dishCount ?></div><div class="delta badge badge-gold"><?= $catCount ?> catégories</div></div>
  <div class="stat"><div class="lbl">Disponibles</div><div class="val" style="color:var(--green-2);"><?= $availCount ?></div><div class="delta badge badge-ok">En ligne</div></div>
  <div class="stat"><div class="lbl">Réservations</div><div class="val" style="color:#8b5a00;"><?= count($todayResv) ?></div><div class="delta badge badge-gold"><?= $pendingResv ?> en attente</div></div>
</div>

<?php if (!empty($recentOrders)): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin:16px 2px 14px;">
  <h2 style="font-size:18px;font-weight:800;margin:0;">Dernières commandes de table</h2>
  <a href="/admin/orders.php" style="color:var(--accent);font-weight:700;font-size:13px;text-decoration:none;">Voir toutes les commandes (<?= $todayOrdersCount ?>) →</a>
</div>
<div class="table-wrap" style="margin-bottom:26px;">
  <table>
    <thead><tr><th>Heure</th><th>Table</th><th>Client</th><th>Plats</th><th>Total</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recentOrders as $ord):
      $ordItems = json_decode($ord['items_json'] ?? '[]', true) ?: [];
      $st = $ord['status'];
      $badge = match ($st) { 'served' => 'badge-ok', 'cancelled' => 'badge-bad', 'preparing' => 'badge-gold', default => 'badge-gold' };
      $stLabel = ['pending' => 'En attente', 'preparing' => 'En cuisine', 'served' => 'Servie', 'completed' => 'Terminée', 'cancelled' => 'Annulée'][$st] ?? $st;
      $itemNames = array_map(fn($it) => ($it['qty'] ?? 1) . 'x ' . ($it['name'] ?? ''), $ordItems);
      $itemsSummary = implode(', ', array_slice($itemNames, 0, 3));
      if (count($itemNames) > 3) $itemsSummary .= '...';
    ?>
      <tr>
        <td style="font-weight:700;color:var(--muted-ink);font-size:13px;"><?= date('H:i', strtotime($ord['created_at'])) ?></td>
        <td style="font-weight:800;color:var(--text);"><?= e($ord['table_number']) ?></td>
        <td><?= e($ord['customer_name'] ?: '—') ?></td>
        <td style="max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px;color:var(--text);"><?= e($itemsSummary) ?></td>
        <td style="font-weight:800;color:var(--accent);"><?= number_format((float)$ord['total_amount'], 2, ',', ' ') ?> <?= e($rest['currency'] ?: 'DH') ?></td>
        <td><span class="badge <?= e($badge) ?>"><?= e($stLabel) ?></span></td>
        <td style="text-align:right;"><a class="icon-btn" href="/admin/orders.php" title="Gérer">→</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($todayResv): ?>
<h2 style="font-size:18px;font-weight:800;margin:10px 2px 14px;">Réservations aujourd'hui</h2>
<div class="table-wrap" style="margin-bottom:26px;">
  <table>
    <thead><tr><th>Heure</th><th>Client</th><th>Taille</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($todayResv as $r):
      $badge = match ($r['status']) { 'confirmed' => 'badge-ok', 'cancelled' => 'badge-bad', default => 'badge-gold' };
      $label = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'cancelled' => 'Annulée'][$r['status']] ?? $r['status'];
    ?>
      <tr>
        <td style="font-weight:800;color:var(--accent);"><?= e($r['time']) ?></td>
        <td><div style="font-weight:700;"><?= e($r['name']) ?></div><div style="color:var(--muted-ink);font-size:12px;"><a href="tel:<?= e($r['phone']) ?>" style="color:inherit;"><?= e($r['phone']) ?></a></div></td>
        <td><span class="badge badge-gold"><?= (int)$r['party_size'] ?> pers.</span></td>
        <td><span class="badge <?= e($badge) ?>"><?= e($label) ?></span></td>
        <td style="text-align:right;"><a class="icon-btn" href="/admin/reservations.php" title="Gérer">→</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<h2 style="font-size:18px;font-weight:800;margin:10px 2px 14px;">Derniers plats ajoutés</h2>
<div class="table-wrap">
  <table>
    <thead><tr><th>Plat</th><th>Catégorie</th><th>Prix</th><th>État</th><th></th></tr></thead>
    <tbody>
    <?php if (!$recent): ?>
      <tr><td colspan="5" style="text-align:center;padding:40px 16px;color:var(--muted-ink);">Aucun plat pour le moment. <a style="color:var(--accent);font-weight:700;" href="/admin/dishes.php?new=1">Ajoutez-en un</a>.</td></tr>
    <?php else: foreach ($recent as $d): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div class="thumb">
              <?php if ($d['photo']): ?><img src="<?= e(UPLOADS_URL . '/' . $d['photo']) ?>" alt=""><?php else: ?><span>★</span><?php endif; ?>
            </div>
            <div><b><?= e($d['name']) ?></b></div>
          </div>
        </td>
        <td><?= e($d['cat'] ?? '—') ?></td>
        <td class="price" style="font-size:14px;"><?= e(money((float)$d['price'])) ?></td>
        <td><?= $d['available'] ? '<span class="badge badge-ok">Disponible</span>' : '<span class="badge badge-bad">Épuisé</span>' ?></td>
        <td class="row-actions">
          <a class="icon-btn" href="/admin/dishes.php?edit=<?= (int)$d['id'] ?>" title="Modifier">✎</a>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../shared/layout_foot.php';

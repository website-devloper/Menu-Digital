<?php
require __DIR__ . '/../shared/config.php';
$user = require_auth();
$rest = current_restaurant($user);
$rid  = (int)$rest['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $map = ['confirm' => 'confirmed', 'cancel' => 'cancelled', 'pend' => 'pending'];
        if (!isset($map[$action])) throw new RuntimeException('Action invalide.');
        db()->prepare('UPDATE reservations SET status = ? WHERE id = ? AND restaurant_id = ?')
            ->execute([$map[$action], $id, $rid]);
        header('Location: /admin/reservations.php?ok=' . rawurlencode('Statut mis à jour.'));
        exit;
    } catch (Throwable $e) {
        header('Location: /admin/reservations.php?err=' . rawurlencode($e->getMessage()));
        exit;
    }
}

$status = $_GET['status'] ?? 'all';
$allowed = ['all', 'pending', 'confirmed', 'cancelled'];
if (!in_array($status, $allowed, true)) $status = 'all';

$sql = 'SELECT * FROM reservations WHERE restaurant_id = ?';
$params = [$rid];
if ($status !== 'all') { $sql .= ' AND status = ?'; $params[] = $status; }
$sql .= ' ORDER BY date DESC, time DESC, id DESC LIMIT 200';
$st = db()->prepare($sql); $st->execute($params);
$list = $st->fetchAll();

$counts = db()->prepare('SELECT status, COUNT(*) AS n FROM reservations WHERE restaurant_id = ? GROUP BY status');
$counts->execute([$rid]);
$C = ['pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
foreach ($counts as $r) $C[$r['status']] = (int)$r['n'];

$PAGE_TITLE = 'Réservations';
$ACTIVE     = 'reservations';
$csrf       = csrf_token();
require __DIR__ . '/../shared/layout.php';
?>

<div class="page-head">
  <div><h1>Réservations</h1><div class="sub">Gérez les demandes faites depuis le menu public.</div></div>
</div>

<div class="stat-row">
  <div class="stat"><div class="lbl">En attente</div><div class="val" style="color:#8b5a00;"><?= (int)$C['pending'] ?></div></div>
  <div class="stat"><div class="lbl">Confirmées</div><div class="val" style="color:var(--green-2);"><?= (int)$C['confirmed'] ?></div></div>
  <div class="stat"><div class="lbl">Annulées</div><div class="val" style="color:var(--bad-ink);"><?= (int)$C['cancelled'] ?></div></div>
  <div class="stat"><div class="lbl">Total</div><div class="val"><?= (int)$C['pending'] + (int)$C['confirmed'] + (int)$C['cancelled'] ?></div></div>
</div>

<div class="toolbar">
  <?php foreach ([['all','Toutes'],['pending','En attente'],['confirmed','Confirmées'],['cancelled','Annulées']] as $f): ?>
    <a class="btn-ghost <?= $status === $f[0] ? 'active' : '' ?>"
       style="padding:8px 16px;<?= $status === $f[0] ? 'background:var(--dark);color:var(--text-onDark);border-color:var(--dark);' : '' ?>"
       href="/admin/reservations.php?status=<?= e($f[0]) ?>"><?= e($f[1]) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Client</th><th>Taille</th><th>Statut</th><th>Notes</th><th style="width:220px;text-align:right;">Actions</th></tr></thead>
    <tbody>
      <?php if (!$list): ?>
        <tr><td colspan="6" style="text-align:center;padding:40px 16px;color:var(--muted-ink);">Aucune réservation.</td></tr>
      <?php else: foreach ($list as $r):
        $badge = match ($r['status']) {
          'confirmed' => 'badge-ok',
          'cancelled' => 'badge-bad',
          default     => 'badge-gold',
        };
        $label = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'cancelled' => 'Annulée'][$r['status']] ?? $r['status'];
      ?>
        <tr>
          <td>
            <div style="font-weight:700;"><?= e($r['date']) ?> · <?= e($r['time']) ?></div>
            <div style="color:var(--muted-ink);font-size:12px;"><?= e($r['created_at']) ?></div>
          </td>
          <td>
            <div style="font-weight:700;"><?= e($r['name']) ?></div>
            <div style="font-size:12.5px;color:var(--accent);">
              <a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a>
              <?php if ($r['email']): ?> · <a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?php endif; ?>
            </div>
          </td>
          <td><span class="badge badge-gold"><?= (int)$r['party_size'] ?> pers.</span></td>
          <td><span class="badge <?= e($badge) ?>"><?= e($label) ?></span></td>
          <td style="color:var(--muted-ink);font-size:13px;max-width:240px;"><?= $r['notes'] ? e($r['notes']) : '—' ?></td>
          <td class="row-actions">
            <?php if ($r['status'] !== 'confirmed'): ?>
              <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="icon-btn" title="Confirmer" style="color:var(--green-2);">✓</button></form>
            <?php endif; ?>
            <?php if ($r['status'] !== 'cancelled'): ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('Annuler cette réservation ?');"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="icon-btn danger" title="Annuler">✕</button></form>
            <?php endif; ?>
            <?php if ($r['status'] !== 'pending'): ?>
              <form method="post" style="display:inline;"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="pend"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="icon-btn" title="En attente">↺</button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../shared/layout_foot.php';

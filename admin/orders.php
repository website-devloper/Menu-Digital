<?php
require __DIR__ . '/../shared/config.php';
$user = require_auth();
$rest = current_restaurant($user);
$pdo = db();

// Ensure table exists
$isPg = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
$pk = $isPg ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$ts = $isPg ? 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP' : 'TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP';

$pdo->exec("CREATE TABLE IF NOT EXISTS orders (
  id             $pk,
  restaurant_id  INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  table_number   TEXT NOT NULL,
  customer_name  TEXT DEFAULT '',
  items_json     TEXT NOT NULL,
  total_amount   REAL NOT NULL DEFAULT 0,
  status         TEXT NOT NULL DEFAULT 'pending',
  notes          TEXT DEFAULT '',
  created_at     $ts
)");

$filterStatus = trim((string)($_GET['status'] ?? ''));

$sql = 'SELECT * FROM orders WHERE restaurant_id = ?';
$params = [$rest['id']];
if ($filterStatus && $filterStatus !== 'all') {
    $sql .= ' AND status = ?';
    $params[] = $filterStatus;
}
$sql .= ' ORDER BY id DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Counts for tabs
$counts = [
    'all'       => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = {$rest['id']}")->fetchColumn(),
    'pending'   => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = {$rest['id']} AND status = 'pending'")->fetchColumn(),
    'preparing' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = {$rest['id']} AND status = 'preparing'")->fetchColumn(),
    'served'    => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = {$rest['id']} AND status = 'served'")->fetchColumn(),
    'completed' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = {$rest['id']} AND status = 'completed'")->fetchColumn(),
];

$PAGE_TITLE = 'Commandes en direct';
$ACTIVE     = 'orders';
require __DIR__ . '/../shared/layout.php';
?>

<style>
  .orders-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 22px;
  }
  .orders-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 24px;
  }
  .orders-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 9999px;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text);
    font-weight: 600;
    font-size: 13.5px;
    text-decoration: none;
    transition: var(--transition);
  }
  .orders-tab:hover {
    border-color: var(--accent);
    color: var(--accent);
  }
  .orders-tab.active {
    background: linear-gradient(135deg, var(--gold), var(--amber));
    color: #241017;
    border-color: transparent;
    box-shadow: 0 4px 14px rgba(241,172,24,.28);
  }
  .orders-tab .tab-badge {
    background: rgba(0,0,0,0.08);
    font-size: 11px;
    padding: 2px 7px;
    border-radius: 999px;
    font-weight: 800;
  }
  .orders-tab.active .tab-badge {
    background: rgba(36,16,23,0.15);
  }

  .orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 20px;
  }

  .order-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-card);
    transition: transform .15s, box-shadow .15s;
    position: relative;
    overflow: hidden;
  }
  .order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(0,0,0,0.08);
  }
  .order-card.status-pending {
    border-left: 5px solid #E11D48;
  }
  .order-card.status-preparing {
    border-left: 5px solid #3B82F6;
  }
  .order-card.status-served {
    border-left: 5px solid #10B981;
  }
  .order-card.status-completed {
    border-left: 5px solid #6B7280;
    opacity: 0.85;
  }
  .order-card.status-cancelled {
    border-left: 5px solid #DC2626;
    opacity: 0.7;
  }

  .order-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
  }
  .order-table-title {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .order-client-name {
    font-size: 13px;
    color: var(--muted-ink);
    margin-top: 3px;
  }
  .order-time-ago {
    font-size: 11.5px;
    color: var(--muted-ink);
    text-align: right;
  }

  .order-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    margin-top: 4px;
  }
  .pill-pending { background: #FFE4E6; color: #BE123C; }
  .pill-preparing { background: #DBEAFE; color: #1D4ED8; }
  .pill-served { background: #D1FAE5; color: #047857; }
  .pill-completed { background: #F3F4F6; color: #4B5563; }
  .pill-cancelled { background: #FEE2E2; color: #991B1B; }

  .order-items-box {
    margin: 14px 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1 1 auto;
  }
  .order-item-line {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    font-size: 14px;
  }
  .order-item-left {
    display: flex;
    align-items: flex-start;
    gap: 8px;
  }
  .order-item-qty {
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text);
    font-weight: 800;
    font-size: 12px;
    padding: 1px 7px;
    border-radius: 6px;
    flex-shrink: 0;
    margin-top: 2px;
  }
  .order-item-name {
    font-weight: 700;
    color: var(--text);
  }
  .order-item-custom {
    font-size: 12px;
    color: #8C827A;
    margin-top: 2px;
    line-height: 1.35;
  }
  .order-item-price {
    font-weight: 700;
    color: var(--text);
    white-space: nowrap;
  }

  .order-card-foot {
    padding-top: 12px;
    border-top: 1px dashed var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .order-total-sum {
    font-size: 18px;
    font-weight: 800;
    color: var(--text);
  }
  .order-actions-bar {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }
  .btn-act {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: transform .1s, background .15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }
  .btn-act:active { transform: scale(0.95); }
  .btn-act-prepare { background: #3B82F6; color: #fff; }
  .btn-act-serve { background: #10B981; color: #fff; }
  .btn-act-done { background: #4B5563; color: #fff; }
  .btn-act-cancel { background: #FEE2E2; color: #991B1B; }
  .btn-act-print { background: var(--surface-2); border: 1px solid var(--border); color: var(--text); }

  /* Audio toggle & pulse indicator */
  .live-pulse {
    display: inline-block;
    width: 8px;
    height: 8px;
    background: #10B981;
    border-radius: 50%;
    margin-right: 6px;
    box-shadow: 0 0 0 rgba(16,185,129, 0.4);
    animation: livePulse 2s infinite;
  }
  @keyframes livePulse {
    0% { box-shadow: 0 0 0 0 rgba(16,185,129, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(16,185,129, 0); }
    100% { box-shadow: 0 0 0 0 rgba(16,185,129, 0); }
  }

  @media print {
    body * { visibility: hidden; }
    .print-ticket, .print-ticket * { visibility: visible; }
    .print-ticket { position: absolute; left: 0; top: 0; width: 80mm; font-size: 12px; }
  }
</style>

<div class="orders-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px;">
      <span class="live-pulse"></span>
      Commandes en direct
    </h1>
    <div class="sub">Les commandes transmises depuis le menu digital s'affichent ici en temps réel.</div>
  </div>
  <div style="display:flex;align-items:center;gap:10px;">
    <button type="button" class="btn-act btn-act-print" id="btnSoundToggle" onclick="toggleSoundAlert()" title="Activer/Désactiver l'alerte sonore">
      <span id="soundIcon">🔊</span> <span id="soundText">Son activé</span>
    </button>
    <button type="button" class="btn-primary" onclick="pollNewOrders(true)">
      ↻ Actualiser
    </button>
  </div>
</div>

<div class="orders-tabs">
  <a href="?status=all" class="orders-tab <?= (!$filterStatus || $filterStatus === 'all') ? 'active' : '' ?>">
    Toutes <span class="tab-badge"><?= $counts['all'] ?></span>
  </a>
  <a href="?status=pending" class="orders-tab <?= $filterStatus === 'pending' ? 'active' : '' ?>">
    ⏳ En attente <span class="tab-badge" style="background:#FFE4E6;color:#BE123C;"><?= $counts['pending'] ?></span>
  </a>
  <a href="?status=preparing" class="orders-tab <?= $filterStatus === 'preparing' ? 'active' : '' ?>">
    🍳 En cuisine <span class="tab-badge"><?= $counts['preparing'] ?></span>
  </a>
  <a href="?status=served" class="orders-tab <?= $filterStatus === 'served' ? 'active' : '' ?>">
    🍽️ Servies <span class="tab-badge"><?= $counts['served'] ?></span>
  </a>
  <a href="?status=completed" class="orders-tab <?= $filterStatus === 'completed' ? 'active' : '' ?>">
    ✓ Terminées <span class="tab-badge"><?= $counts['completed'] ?></span>
  </a>
</div>

<div class="orders-grid" id="ordersGrid">
  <?php if (empty($orders)): ?>
    <div style="grid-column: 1 / -1; background:var(--surface); border:1px solid var(--border); border-radius:18px; padding:48px 20px; text-align:center; color:var(--muted-ink);">
      <div style="font-size:36px;margin-bottom:10px;">🍽️</div>
      <div style="font-size:16px;font-weight:700;color:var(--text);margin-bottom:4px;">Aucune commande pour le moment</div>
      <div style="font-size:13.5px;">Dès qu'un client clique sur "Confirmer la commande" sur une table, elle apparaîtra ici instantanément !</div>
    </div>
  <?php else: ?>
    <?php foreach ($orders as $ord):
      $items = json_decode($ord['items_json'] ?? '[]', true) ?: [];
      $st = $ord['status'];
      $stLabel = match($st) {
        'pending'   => 'En attente',
        'preparing' => 'En préparation',
        'served'    => 'Servie',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
        default     => $st
      };
      $stClass = 'pill-' . $st;
      $createdTime = date('H:i', strtotime($ord['created_at']));
    ?>
      <div class="order-card status-<?= e($st) ?>" id="orderCard_<?= (int)$ord['id'] ?>" data-id="<?= (int)$ord['id'] ?>">
        <div class="order-card-top">
          <div>
            <div class="order-table-title">
              <span>📍</span> <?= e($ord['table_number']) ?>
            </div>
            <?php if (!empty($ord['customer_name'])): ?>
              <div class="order-client-name">👤 <?= e($ord['customer_name']) ?></div>
            <?php endif; ?>
          </div>
          <div style="text-align:right;">
            <div class="order-time-ago">#<?= (int)$ord['id'] ?> · <?= e($createdTime) ?></div>
            <span class="order-status-pill <?= $stClass ?>"><?= e($stLabel) ?></span>
          </div>
        </div>

        <div class="order-items-box">
          <?php foreach ($items as $it): ?>
            <div class="order-item-line">
              <div class="order-item-left">
                <span class="order-item-qty"><?= (int)$it['qty'] ?>x</span>
                <div>
                  <div class="order-item-name"><?= e($it['name']) ?></div>
                  <?php if (!empty($it['customSummary'])): ?>
                    <div class="order-item-custom">↳ <?= e($it['customSummary']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="order-item-price"><?= number_format((float)$it['price'] * (int)$it['qty'], 2, ',', ' ') ?> <?= e($rest['currency'] ?: 'DH') ?></div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="order-card-foot">
          <div>
            <div style="font-size:11px;color:var(--muted-ink);text-transform:uppercase;font-weight:700;">Total</div>
            <div class="order-total-sum"><?= number_format((float)$ord['total_amount'], 2, ',', ' ') ?> <?= e($rest['currency'] ?: 'DH') ?></div>
          </div>

          <div class="order-actions-bar">
            <?php if ($st === 'pending'): ?>
              <button type="button" class="btn-act btn-act-prepare" onclick="setOrderStatus(<?= (int)$ord['id'] ?>, 'preparing')">
                🍳 Préparer
              </button>
            <?php elseif ($st === 'preparing'): ?>
              <button type="button" class="btn-act btn-act-serve" onclick="setOrderStatus(<?= (int)$ord['id'] ?>, 'served')">
                🍽️ Servir
              </button>
            <?php elseif ($st === 'served'): ?>
              <button type="button" class="btn-act btn-act-done" onclick="setOrderStatus(<?= (int)$ord['id'] ?>, 'completed')">
                ✓ Terminer
              </button>
            <?php endif; ?>

            <?php if ($st !== 'completed' && $st !== 'cancelled'): ?>
              <button type="button" class="btn-act btn-act-cancel" onclick="setOrderStatus(<?= (int)$ord['id'] ?>, 'cancelled')" title="Annuler">
                ✕
              </button>
            <?php endif; ?>

            <button type="button" class="btn-act btn-act-print" onclick="printReceipt(<?= (int)$ord['id'] ?>)" title="Imprimer Ticket">
              🖨️
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
let soundEnabled = true;
let maxOrderId = <?= !empty($orders) ? (int)$orders[0]['id'] : 0 ?>;

// Web Audio API synthesized alert chime
function playOrderChime() {
  if (!soundEnabled) return;
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc1 = ctx.createOscillator();
    const osc2 = ctx.createOscillator();
    const gain = ctx.createGain();

    osc1.type = 'sine';
    osc1.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
    osc1.frequency.setValueAtTime(880.00, ctx.currentTime + 0.15); // A5

    gain.gain.setValueAtTime(0.3, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.6);

    osc1.connect(gain);
    gain.connect(ctx.destination);

    osc1.start();
    osc1.stop(ctx.currentTime + 0.6);
  } catch (e) {
    console.log('Audio autoplay prevented or unsupported');
  }
}

function toggleSoundAlert() {
  soundEnabled = !soundEnabled;
  document.getElementById('soundIcon').innerText = soundEnabled ? '🔊' : '🔇';
  document.getElementById('soundText').innerText = soundEnabled ? 'Son activé' : 'Son coupé';
  if (soundEnabled) playOrderChime();
}

async function setOrderStatus(orderId, status) {
  try {
    const res = await fetch('/admin/orders_api.php?action=update_status', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId, status: status })
    });
    const data = await res.json();
    if (data.success) {
      // Reload current view to keep tabs and counts in sync
      window.location.reload();
    } else {
      alert(data.error || 'Erreur lors de la mise à jour.');
    }
  } catch (err) {
    alert('Erreur réseau.');
  }
}

async function pollNewOrders(manual = false) {
  try {
    const res = await fetch(`/admin/orders_api.php?action=poll&since_id=${maxOrderId}`);
    const data = await res.json();
    if (data.success && data.orders && data.orders.length > 0) {
      playOrderChime();
      // Reload page to reflect new orders & update counts
      window.location.reload();
    } else if (manual) {
      window.location.reload();
    }
  } catch (e) {}
}

function printReceipt(orderId) {
  const card = document.getElementById(`orderCard_${orderId}`);
  if (!card) return;
  const printWin = window.open('', '_blank', 'width=350,height=500');
  printWin.document.write(`
    <!doctype html>
    <html>
    <head>
      <title>Ticket Commande #${orderId}</title>
      <style>
        body { font-family: monospace; font-size: 13px; margin: 10px; color: #000; }
        .hdr { text-align: center; border-bottom: 1px dashed #000; padding-bottom: 8px; margin-bottom: 10px; }
        .tbl { width: 100%; border-collapse: collapse; }
        .tbl td { padding: 4px 0; }
        .tot { border-top: 1px dashed #000; margin-top: 8px; padding-top: 8px; font-weight: bold; font-size: 15px; }
      </style>
    </head>
    <body>
      <div class="hdr">
        <h3><?= e($rest['name']) ?></h3>
        <div>*** TICKET CUISINE ***</div>
        <div>Commande #${orderId}</div>
      </div>
      <div>${card.querySelector('.order-card-top').innerHTML}</div>
      <hr style="border:none;border-top:1px dashed #000;margin:10px 0;">
      <div>${card.querySelector('.order-items-box').innerHTML}</div>
      <div class="tot">${card.querySelector('.order-total-sum').innerText}</div>
      <div style="text-align:center;margin-top:14px;font-size:11px;">Imprimé le ${new Date().toLocaleTimeString()}</div>
      <script>
        window.onload = function() { window.print(); window.close(); }
      <\/script>
    </body>
    </html>
  `);
  printWin.document.close();
}

// Auto-poll every 6 seconds for live orders
setInterval(pollNewOrders, 6000);
</script>

<?php require __DIR__ . '/../shared/layout_foot.php'; ?>

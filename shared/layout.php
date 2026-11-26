<?php
// Admin layout — plum sidebar + gold active state, mirroring restaurant2/admin.
// Caller must have set $PAGE_TITLE, $ACTIVE (one of: dashboard, categories, dishes, qr, scan), $user, $rest.

$NAV = [
  ['dashboard',    'Tableau de bord', '/admin/',                 'grid'],
  ['orders',       'Commandes',       '/admin/orders.php',       'shopping-bag'],
  ['categories',   'Catégories',      '/admin/categories.php',   'layers'],
  ['dishes',       'Plats',           '/admin/dishes.php',       'utensils'],
  ['reservations', 'Réservations',    '/admin/reservations.php', 'calendar'],
  ['promotions',   'Promotions',      '/admin/promotions.php',   'tag'],
  ['settings',     'Paramètres',      '/admin/settings.php',     'cog'],
  ['qr',           'QR Code',         '/admin/qr.php',           'qr'],
  ['scan',         'Scanner',         '/admin/scan.php',         'camera'],
];

function nav_icon(string $k): string {
  // Material Symbols Rounded (Google Fonts). Loaded via @import in /shared/theme.css.
  $map = [
    'grid'         => 'space_dashboard',
    'shopping-bag' => 'shopping_bag',
    'layers'       => 'category',
    'utensils'     => 'restaurant_menu',
    'qr'           => 'qr_code_2',
    'camera'       => 'photo_camera',
    'calendar'     => 'event',
    'tag'          => 'sell',
    'cog'          => 'settings',
    'logout'       => 'logout',
  ];
  $name = $map[$k] ?? '';
  if ($name === '') return '';
  return "<span class=\"msi sm\" aria-hidden=\"true\">{$name}</span>";
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($PAGE_TITLE ?? 'Admin') ?> · <?= e($rest['name'] ?? 'Wevtex') ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/shared/theme.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,500,0,0&display=swap" rel="stylesheet">
<style>
  .app { display:grid; grid-template-columns: 240px 1fr; min-height:100vh; }
  @media (max-width: 860px) { .app { grid-template-columns: 1fr; } }

  /* Sidebar — plum anchor */
  .sidebar {
    background: var(--dark); color: var(--text-onDark);
    padding: 20px 14px; display:flex; flex-direction:column; gap: 18px;
    position: sticky; top: 0; height: 100vh;
  }
  @media (max-width: 860px) {
    .sidebar { position: static; height: auto; flex-direction: row; align-items: center; padding: 10px 14px; overflow-x: auto; }
    .brand-block, .nav-section-title, .side-foot { display: none; }
    .nav-list { display:flex; flex-direction: row; gap: 6px; }
  }
  .brand-block { display:flex;align-items:center;gap:10px; padding: 6px 4px 14px; border-bottom: 1px solid rgba(247,243,236,.1); }
  .brand-mark {
    width:36px;height:36px;border-radius: 9px;background:linear-gradient(135deg,var(--gold),var(--amber));
    display:grid;place-items:center;color:#241017;font-family:var(--font-heading);font-weight:800;font-size:16px;
  }
  .brand-name { font-weight:800; font-size:15px; letter-spacing: -.01em; }
  .brand-name span { color: var(--gold); }
  .brand-sub { font-size: 11px; color: rgba(247,243,236,.5); letter-spacing: .08em; text-transform: uppercase;}

  .nav-section-title { font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: rgba(247,243,236,.42); padding: 10px 10px 6px; }
  .nav-list { list-style:none; display: flex; flex-direction: column; gap: 2px; }
  .nav-link {
    display:flex; align-items:center; gap: 12px; padding: 11px 14px; border-radius: 12px;
    color: rgba(247,243,236,.85); font-weight: 700; font-size: 14px; letter-spacing: -.005em;
    transition: var(--transition); white-space: nowrap;
  }
  .nav-link .msi { font-size: 20px; opacity: .9; }
  .nav-link:hover { background: rgba(255,255,255,.08); color:#fff; }
  .nav-link.active {
    background: linear-gradient(135deg, var(--gold), var(--amber));
    color:#241017; font-weight: 900;
    box-shadow: 0 10px 24px rgba(241,172,24,.38);
  }
  .nav-link.active .msi { opacity: 1; font-variation-settings: 'FILL' 1, 'wght' 700, 'GRAD' 0, 'opsz' 24; }
  .side-foot { margin-top: auto; padding-top: 12px; border-top: 1px solid rgba(247,243,236,.1); display:grid; gap: 8px; }
  .side-user {
    display:flex; align-items:center; gap:10px; padding: 10px 12px;
    background: rgba(255,255,255,.055); border:1px solid rgba(247,243,236,.1); border-radius: 12px;
  }
  .side-user .av { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--gold), var(--amber)); color:#241017; display:grid;place-items:center; font-weight:800;}
  .side-user .nm { font-size: 13px; font-weight: 700;}
  .side-user .em { font-size: 11px; color: rgba(247,243,236,.55); }
  .logout-btn {
    display:flex; align-items:center; gap: 8px; padding: 10px 12px; border-radius: 10px;
    background: rgba(255,255,255,.08); color: var(--text-onDark); font-weight: 600; justify-content: center;
  }
  .logout-btn:hover { background: rgba(255,255,255,.14); }

  /* Main */
  main.main { padding: 26px 28px 60px; max-width: 1200px; width: 100%; }
  @media (max-width: 640px) { main.main { padding: 18px 16px 50px; } }

  .page-head { display:flex; align-items:center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
  .page-head h1 { font-size: 30px; font-weight: 900; letter-spacing: -.025em; }
  .page-head .sub { color: var(--muted-ink); font-size: 14px; font-weight: 600; margin-top: 4px; }

  .stat-row { display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 28px; }
  .stat {
    padding: 22px; background: var(--surface); border:1px solid var(--border); border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card), var(--shadow-inner);
    transition: var(--transition-smooth); position: relative; overflow: hidden;
  }
  .stat::before {
    content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--amber));
    opacity: 0; transition: opacity .3s;
  }
  .stat:hover { transform: translateY(-3px); box-shadow: var(--shadow-hover), var(--shadow-inner); }
  .stat:hover::before { opacity: 1; }
  .stat .lbl { font-size: 11px; color: var(--muted-ink); letter-spacing: .1em; text-transform: uppercase; font-weight: 800; }
  .stat .val { font-family: var(--font-heading); font-size: 34px; font-weight: 900; margin-top: 6px; color: var(--dark); letter-spacing: -.025em;}
  .stat .delta { font-size: 12px; font-weight: 700; margin-top: 4px; }

  .table-wrap {
    background: var(--surface); border:1px solid var(--border); border-radius: var(--radius-lg);
    overflow:hidden; box-shadow: var(--shadow-card), var(--shadow-inner);
  }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 14px 18px; text-align: left; font-size: 14px; font-weight: 600; border-bottom: 1px solid var(--border); vertical-align: middle;}
  th { font-size: 11px; color: var(--muted-ink); letter-spacing: .1em; text-transform: uppercase; font-weight: 800; background: var(--surface-2); }
  tbody tr { transition: background .15s; }
  tbody tr:hover { background: var(--surface-2); }
  tr:last-child td { border-bottom: none; }

  .thumb { width: 44px; height: 44px; border-radius: 8px; overflow: hidden; background: var(--track); display:grid; place-items:center; color: var(--muted-ink); flex-shrink:0;}
  .thumb img { width:100%;height:100%;object-fit:cover; }

  .row-actions { display:flex; gap: 6px; justify-content: flex-end; }
  .icon-btn {
    width:34px;height:34px;display:grid;place-items:center;border-radius: 8px;
    background: var(--surface); border:1px solid var(--border); color: var(--text); transition: var(--transition);
  }
  .icon-btn:hover { color: var(--accent); border-color: var(--accent); }
  .icon-btn.danger:hover { color: var(--bad-ink); border-color: var(--bad-ink); background: var(--bad-bg); }

  .toolbar { display:flex; align-items:center; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
  .toolbar .input { max-width: 260px; }
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand-block">
      <div class="brand-mark"><?= e(mb_substr($rest['name'] ?? 'W', 0, 1)) ?></div>
      <div>
        <div class="brand-name">Wevtex<span>·</span>Menu</div>
        <div class="brand-sub"><?= e($rest['name'] ?? '') ?></div>
      </div>
    </div>

    <div>
      <div class="nav-section-title">Menu principal</div>
      <ul class="nav-list">
        <?php foreach ($NAV as $it): [$k, $label, $href, $icon] = $it;
          $pendingBadge = '';
          if ($k === 'orders') {
            try {
              $pCnt = (int)db()->query("SELECT COUNT(*) FROM orders WHERE restaurant_id = " . (int)$rest['id'] . " AND status = 'pending'")->fetchColumn();
              if ($pCnt > 0) {
                $pendingBadge = '<span class="badge" style="background:#E11D48;color:#fff;font-size:11px;font-weight:800;border-radius:10px;padding:2px 7px;margin-left:auto;">' . $pCnt . '</span>';
              }
            } catch (Throwable $e) {}
          }
        ?>
          <li>
            <a class="nav-link <?= $ACTIVE === $k ? 'active' : '' ?>" href="<?= e($href) ?>">
              <?= nav_icon($icon) ?><span><?= e($label) ?></span><?= $pendingBadge ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="side-foot">
      <a class="nav-link" style="justify-content:center;background:transparent;border:1px solid rgba(247,243,236,.22);"
         href="/menu.php?r=<?= e($rest['slug'] ?? '') ?>" target="_blank">
        Voir le menu public ↗
      </a>
      <div class="side-user">
        <div class="av"><?= e(mb_substr($user['display_name'] ?? $user['email'], 0, 1)) ?></div>
        <div>
          <div class="nm"><?= e($user['display_name'] ?: $user['email']) ?></div>
          <div class="em"><?= e($user['email']) ?></div>
        </div>
      </div>
      <a class="logout-btn" href="/admin/logout.php"><?= nav_icon('logout') ?><span>Déconnexion</span></a>
    </div>
  </aside>

  <main class="main">

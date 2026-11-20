<?php
// Admin layout — plum sidebar + gold active state, mirroring restaurant2/admin.
// Caller must have set $PAGE_TITLE, $ACTIVE (one of: dashboard, categories, dishes, qr, scan), $user, $rest.

$NAV = [
  ['dashboard',    'Tableau de bord', '/admin/',                 'grid'],
  ['categories',   'Catégories',      '/admin/categories.php',   'layers'],
  ['dishes',       'Plats',           '/admin/dishes.php',       'utensils'],
  ['reservations', 'Réservations',    '/admin/reservations.php', 'calendar'],
  ['promotions',   'Promotions',      '/admin/promotions.php',   'tag'],
  ['settings',     'Paramètres',      '/admin/settings.php',     'cog'],
  ['qr',           'QR Code',         '/admin/qr.php',           'qr'],
  ['scan',         'Scanner',         '/admin/scan.php',         'camera'],
];

function nav_icon(string $k): string {
  $s = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';
  return match($k) {
    'grid'     => "<svg $s><rect x='3' y='3' width='7' height='7' rx='1'/><rect x='14' y='3' width='7' height='7' rx='1'/><rect x='3' y='14' width='7' height='7' rx='1'/><rect x='14' y='14' width='7' height='7' rx='1'/></svg>",
    'layers'   => "<svg $s><path d='m12 2 10 5-10 5-10-5z'/><path d='m2 12 10 5 10-5'/><path d='m2 17 10 5 10-5'/></svg>",
    'utensils' => "<svg $s><path d='M3 2v7a3 3 0 0 0 6 0V2'/><path d='M6 11v11'/><path d='M17 2v20'/><path d='M14 2h6a0 0 0 0 1 0 0v6a3 3 0 0 1-3 3h-3V2z'/></svg>",
    'qr'       => "<svg $s><rect x='3' y='3' width='7' height='7' rx='1'/><rect x='14' y='3' width='7' height='7' rx='1'/><rect x='3' y='14' width='7' height='7' rx='1'/><path d='M14 14h3v3h-3zM18 18h3v3h-3zM14 18h3v3h-3z'/></svg>",
    'camera'   => "<svg $s><path d='M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z'/><circle cx='12' cy='13' r='4'/></svg>",
    'calendar' => "<svg $s><rect x='3' y='4' width='18' height='18' rx='2' ry='2'/><line x1='16' y1='2' x2='16' y2='6'/><line x1='8' y1='2' x2='8' y2='6'/><line x1='3' y1='10' x2='21' y2='10'/></svg>",
    'tag'      => "<svg $s><path d='M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z'/><line x1='7' y1='7' x2='7.01' y2='7'/></svg>",
    'cog'      => "<svg $s><circle cx='12' cy='12' r='3'/><path d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z'/></svg>",
    'logout'   => "<svg $s><path d='M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4'/><polyline points='16 17 21 12 16 7'/><line x1='21' y1='12' x2='9' y2='12'/></svg>",
    default    => ''
  };
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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    display:flex; align-items:center; gap: 10px; padding: 10px 12px; border-radius: 12px;
    color: rgba(247,243,236,.82); font-weight: 600; transition: var(--transition);
    white-space: nowrap;
  }
  .nav-link:hover { background: rgba(255,255,255,.06); color:#fff; }
  .nav-link.active {
    background: linear-gradient(135deg, var(--gold), var(--amber));
    color:#241017;
    box-shadow: 0 8px 20px rgba(241,172,24,.32);
  }
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
  .page-head h1 { font-size: 26px; font-weight: 800; letter-spacing: -.02em; }
  .page-head .sub { color: var(--muted-ink); font-size: 14px; margin-top: 3px; }

  .stat-row { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 26px; }
  .stat {
    padding: 18px; background: var(--surface); border:1px solid var(--border); border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
  }
  .stat .lbl { font-size: 12px; color: var(--muted-ink); letter-spacing: .08em; text-transform: uppercase; font-weight: 700; }
  .stat .val { font-family: var(--font-heading); font-size: 30px; font-weight: 800; margin-top: 4px; color: var(--dark);}
  .stat .delta { font-size: 12px; margin-top: 4px; }

  .table-wrap {
    background: var(--surface); border:1px solid var(--border); border-radius: var(--radius-lg);
    overflow:hidden; box-shadow: var(--shadow-card);
  }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 12px 16px; text-align: left; font-size: 14px; border-bottom: 1px solid var(--border); vertical-align: middle;}
  th { font-size: 12px; color: var(--muted-ink); letter-spacing: .08em; text-transform: uppercase; font-weight: 700; background: var(--surface-2); }
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
        <?php foreach ($NAV as $it): [$k, $label, $href, $icon] = $it; ?>
          <li>
            <a class="nav-link <?= $ACTIVE === $k ? 'active' : '' ?>" href="<?= e($href) ?>">
              <?= nav_icon($icon) ?><span><?= e($label) ?></span>
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

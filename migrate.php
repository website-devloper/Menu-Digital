<?php
// migrate.php — idempotent schema upgrade for v2 features.
// Safe to run multiple times. Open /migrate.php in the browser once.

require __DIR__ . '/shared/config.php';
$pdo = db();

function col_exists(PDO $pdo, string $table, string $col): bool {
    foreach ($pdo->query("PRAGMA table_info($table)") as $r) {
        if (strcasecmp($r['name'], $col) === 0) return true;
    }
    return false;
}

$log = [];

// ---- restaurants: phone, hours (JSON), address ----
foreach ([
    'phone'   => "ALTER TABLE restaurants ADD COLUMN phone   TEXT",
    'hours'   => "ALTER TABLE restaurants ADD COLUMN hours   TEXT",
    'address' => "ALTER TABLE restaurants ADD COLUMN address TEXT",
] as $col => $sql) {
    if (!col_exists($pdo, 'restaurants', $col)) { $pdo->exec($sql); $log[] = "restaurants + $col"; }
}

// ---- dishes: flags (csv), spice (0-3), is_special (menu du jour) ----
foreach ([
    'flags'      => "ALTER TABLE dishes ADD COLUMN flags      TEXT    DEFAULT ''",
    'spice'      => "ALTER TABLE dishes ADD COLUMN spice      INTEGER DEFAULT 0",
    'is_special' => "ALTER TABLE dishes ADD COLUMN is_special INTEGER DEFAULT 0",
] as $col => $sql) {
    if (!col_exists($pdo, 'dishes', $col)) { $pdo->exec($sql); $log[] = "dishes + $col"; }
}

// ---- dish_photos (gallery, max 4/dish enforced in app) ----
$pdo->exec("
CREATE TABLE IF NOT EXISTS dish_photos (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,
  dish_id  INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
  photo    TEXT    NOT NULL,
  position INTEGER NOT NULL DEFAULT 0,
  is_cover INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_photos_dish ON dish_photos(dish_id, position);
");

// ---- reservations ----
$pdo->exec("
CREATE TABLE IF NOT EXISTS reservations (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id  INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  name           TEXT    NOT NULL,
  phone          TEXT    NOT NULL,
  email          TEXT,
  party_size     INTEGER NOT NULL,
  date           TEXT    NOT NULL,   -- YYYY-MM-DD
  time           TEXT    NOT NULL,   -- HH:MM
  notes          TEXT,
  status         TEXT    NOT NULL DEFAULT 'pending',  -- pending|confirmed|cancelled
  created_at     TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_resv_rest ON reservations(restaurant_id, date, time);
");

// ---- promotions ----
$pdo->exec("
CREATE TABLE IF NOT EXISTS promotions (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id  INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  name           TEXT    NOT NULL,
  scope_type     TEXT    NOT NULL,          -- 'all' | 'category' | 'dish'
  scope_id       INTEGER,                   -- category_id or dish_id (NULL when 'all')
  discount_pct   INTEGER NOT NULL DEFAULT 0,-- 0..100
  start_date     TEXT,                      -- YYYY-MM-DD (nullable → always)
  end_date       TEXT,
  start_time     TEXT,                      -- HH:MM (nullable → all day)
  end_time       TEXT,
  days_mask      INTEGER NOT NULL DEFAULT 127, -- bitfield: 1=Mon ... 64=Sun, default = all 7
  active         INTEGER NOT NULL DEFAULT 1,
  created_at     TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_promo_rest ON promotions(restaurant_id, active);
");

// ---- Seed defaults on the first restaurant so the demo works out of the box ----
$r = $pdo->query('SELECT * FROM restaurants LIMIT 1')->fetch();
if ($r) {
    $rid = (int)$r['id'];

    // Phone: WhatsApp-ready E.164 fragment (Morocco example)
    if (empty($r['phone'])) {
        $pdo->prepare('UPDATE restaurants SET phone = ? WHERE id = ?')->execute(['212600000000', $rid]);
        $log[] = "seed phone";
    }
    // Hours: Mon-Sun 11:00-23:00 except Mon closed
    if (empty($r['hours'])) {
        $hours = [
            'mon' => ['closed' => 1, 'open' => '', 'close' => ''],
            'tue' => ['closed' => 0, 'open' => '11:00', 'close' => '23:00'],
            'wed' => ['closed' => 0, 'open' => '11:00', 'close' => '23:00'],
            'thu' => ['closed' => 0, 'open' => '11:00', 'close' => '23:00'],
            'fri' => ['closed' => 0, 'open' => '11:00', 'close' => '00:00'],
            'sat' => ['closed' => 0, 'open' => '11:00', 'close' => '00:00'],
            'sun' => ['closed' => 0, 'open' => '12:00', 'close' => '22:00'],
        ];
        $pdo->prepare('UPDATE restaurants SET hours = ? WHERE id = ?')->execute([json_encode($hours), $rid]);
        $log[] = "seed hours";
    }
    if (empty($r['address'])) {
        $pdo->prepare('UPDATE restaurants SET address = ? WHERE id = ?')->execute(['12 rue des Jardins, Marrakech', $rid]);
        $log[] = "seed address";
    }

    // Seed a few dish flags, spice levels, and 2 "menu du jour" picks
    $flagMap = [
      'Tajine de légumes épicés' => ['flags' => 'vegan,halal,gluten-free', 'spice' => 2],
      'Salade Zaalouk'           => ['flags' => 'vegan,halal,gluten-free', 'spice' => 1],
      'Pizza 4 fromages'         => ['flags' => 'vegetarien,lactose',      'spice' => 0],
      'Pizza Marrakchia'         => ['flags' => 'halal',                   'spice' => 2],
      'Burger de bœuf fermier'   => ['flags' => 'halal,lactose',           'spice' => 0],
      'Harira traditionnelle'    => ['flags' => 'halal,gluten',            'spice' => 1],
      'Tacos à l\'avocat'        => ['flags' => 'vegan,halal',             'spice' => 0],
      'Thé à la menthe'          => ['flags' => 'vegan,halal,gluten-free', 'spice' => 0],
    ];
    $upd = $pdo->prepare('UPDATE dishes SET flags = ?, spice = ? WHERE restaurant_id = ? AND name = ? AND (flags IS NULL OR flags = "")');
    foreach ($flagMap as $name => $v) {
        $upd->execute([$v['flags'], $v['spice'], $rid, $name]);
    }
    $log[] = "seed dietary flags";

    // Menu du jour: Harira + Tajine de légumes
    $specials = ['Harira traditionnelle', 'Tajine de légumes épicés'];
    foreach ($specials as $n) {
        $pdo->prepare('UPDATE dishes SET is_special = 1 WHERE restaurant_id = ? AND name = ?')->execute([$rid, $n]);
    }
    $log[] = "seed menu du jour";

    // Demo promotion: pizzas -20% between 18:00 and 20:00 every day
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM promotions WHERE restaurant_id = $rid")->fetchColumn();
    if ($cnt === 0) {
        $pizzasCatId = (int)$pdo->prepare('SELECT id FROM categories WHERE restaurant_id = ? AND name = ?')
            ->execute([$rid, 'Pizzas']) ? 0 : 0;
        $s = $pdo->prepare('SELECT id FROM categories WHERE restaurant_id = ? AND name = ?');
        $s->execute([$rid, 'Pizzas']);
        $pizzasCatId = (int)$s->fetchColumn();
        if ($pizzasCatId) {
            $pdo->prepare('INSERT INTO promotions (restaurant_id, name, scope_type, scope_id, discount_pct, start_time, end_time) VALUES (?,?,?,?,?,?,?)')
                ->execute([$rid, 'Happy hour pizzas', 'category', $pizzasCatId, 20, '18:00', '20:00']);
            $log[] = "seed Happy hour pizzas (-20%)";
        }
    }
}

?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><title>Migration · Wevtex Menu</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/shared/theme.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{display:grid;place-items:center;min-height:100vh;padding:24px;}
.panel{max-width:560px;width:100%;padding:32px;background:var(--surface);border-radius:var(--radius-xl);box-shadow:var(--shadow-card);border:1px solid var(--border);}
.k{font-family:ui-monospace,Menlo,Consolas,monospace;background:var(--track);padding:2px 6px;border-radius:4px;font-size:12px;}
li{margin:4px 0;}
</style>
</head><body>
<div class="panel">
  <h1 style="font-size:24px;margin-bottom:8px;">Mise à jour terminée <span class="star">★</span></h1>
  <p style="color:var(--muted-ink);margin-bottom:16px;">La base contient désormais: allergènes, menu du jour, réservations, horaires, promotions et galerie photo.</p>

  <?php if ($log): ?>
    <h3 style="font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:var(--accent);font-weight:800;margin:12px 0 8px;">Opérations appliquées</h3>
    <ul style="margin-left:18px;color:var(--muted-ink);font-size:14px;">
      <?php foreach ($log as $l): ?><li><span class="k"><?= e($l) ?></span></li><?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>La base est à jour, rien à faire.</p>
  <?php endif; ?>

  <div style="display:flex;gap:10px;margin-top:22px;flex-wrap:wrap;">
    <a class="btn-primary" href="/admin/">Ouvrir le dashboard</a>
    <a class="btn-ghost" href="/menu.php?r=foores">Voir le menu public</a>
  </div>
</div>
</body></html>

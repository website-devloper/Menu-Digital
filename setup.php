<?php
// Wevtex Digital Menu — one-shot DB bootstrap + demo seed.
// Open /setup.php once in your browser (or run `php setup.php` via CLI).

require __DIR__ . '/shared/config.php';

$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS restaurants (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  slug        TEXT    UNIQUE NOT NULL,
  name        TEXT    NOT NULL,
  tagline     TEXT,
  logo        TEXT,
  currency    TEXT    NOT NULL DEFAULT 'DH',
  created_at  TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  email         TEXT    UNIQUE NOT NULL,
  password_hash TEXT    NOT NULL,
  display_name  TEXT,
  created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  name          TEXT    NOT NULL,
  position      INTEGER NOT NULL DEFAULT 0,
  created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dishes (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  restaurant_id INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  category_id   INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  name          TEXT    NOT NULL,
  description   TEXT,
  price         REAL    NOT NULL DEFAULT 0,
  photo         TEXT,
  available     INTEGER NOT NULL DEFAULT 1,
  position      INTEGER NOT NULL DEFAULT 0,
  created_at    TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_cat_rest  ON categories(restaurant_id, position);
CREATE INDEX IF NOT EXISTS idx_dish_rest ON dishes(restaurant_id, category_id, position);
");

$restCount = (int)$pdo->query('SELECT COUNT(*) FROM restaurants')->fetchColumn();
$seeded = false;

if ($restCount === 0) {
    $pdo->prepare("INSERT INTO restaurants (slug, name, tagline) VALUES (?,?,?)")
        ->execute(['foores', 'Foores', 'Cuisine marocaine contemporaine']);
    $rid = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO users (restaurant_id, email, password_hash, display_name) VALUES (?,?,?,?)")
        ->execute([$rid, 'admin@foores.ma', password_hash('admin1234', PASSWORD_DEFAULT), 'Admin Foores']);

    $cats = ['Entrées', 'Plats', 'Pizzas', 'Desserts', 'Boissons'];
    foreach ($cats as $i => $c) {
        $pdo->prepare("INSERT INTO categories (restaurant_id, name, position) VALUES (?,?,?)")
            ->execute([$rid, $c, $i]);
    }
    $catIds = $pdo->prepare("SELECT id, name FROM categories WHERE restaurant_id = ? ORDER BY position");
    $catIds->execute([$rid]);
    $byName = [];
    foreach ($catIds as $r) { $byName[$r['name']] = (int)$r['id']; }

    // Demo photos reused from the Foores restaurant project (see README).
    $dishes = [
        ['Entrées',  'Harira traditionnelle',     "Soupe marocaine aux lentilles, pois chiches et épices.", 35,  1, '1.jpg'],
        ['Entrées',  'Salade Zaalouk',            "Caviar d'aubergines fumées, tomates et coriandre.",     42,  1, '2.jpg'],
        ['Plats',    'Tajine agneau pruneaux',    "Lent cuisson aux amandes, miel et pruneaux moelleux.",  120,  1, '3.jpg'],
        ['Plats',    'Couscous royal',            "Semoule roulée, légumes, agneau, poulet et merguez.",   135,  1, '4.jpg'],
        ['Plats',    'Pastilla au pigeon',        "Feuilleté croustillant sucré-salé, amandes et cannelle.", 95, 0, '5.jpg'],
        ['Pizzas',   'Pizza Marrakchia',          "Base tomate, merguez, olives, oignon rouge et coriandre.", 85, 1, '6.jpg'],
        ['Pizzas',   'Pizza 4 fromages',          "Mozzarella, chèvre, bleu, parmesan.",                    80,  1, '7.jpg'],
        ['Desserts', 'Chebakia au miel',          "Fleur de sésame enrobée de miel et eau de fleur d'oranger.", 28, 1, '8.jpg'],
        ['Desserts', "Crème brûlée à la fleur d'oranger", "Vanille, zeste d'orange, caramel craquant.", 45, 1, '9.jpg'],
        ['Boissons', 'Thé à la menthe',           "Infusion traditionnelle servie à la marocaine.",         18,  1, '10.jpg'],
        ['Boissons', "Jus d'avocat",              "Onctueux, lait, amandes effilées.",                      25,  1, '11.jpg'],
    ];
    $ins = $pdo->prepare("INSERT INTO dishes (restaurant_id, category_id, name, description, price, photo, available, position) VALUES (?,?,?,?,?,?,?,?)");
    foreach ($dishes as $i => $d) {
        $ins->execute([$rid, $byName[$d[0]], $d[1], $d[2], $d[3], $d[5], $d[4], $i]);
    }
    $seeded = true;
}

?><!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><title>Setup · Wevtex Menu</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/shared/theme.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body{display:grid;place-items:center;min-height:100vh;padding:24px;}
.panel{max-width:560px;width:100%;padding:36px;background:var(--surface);border-radius:var(--radius-xl);box-shadow:var(--shadow-card);border:1px solid var(--border);}
.ok{color:var(--green-2);font-weight:700;}
.k{font-family:ui-monospace,Menlo,Consolas,monospace;background:var(--track);padding:2px 6px;border-radius:4px;}
</style>
</head><body>
<div class="panel">
  <h1 style="font-size:26px;margin-bottom:6px;">Installation terminée <span class="star">★</span></h1>
  <p style="color:var(--muted-ink);margin-bottom:20px;">Base de données initialisée à <span class="k"><?= e(DB_PATH) ?></span>.</p>

  <?php if ($seeded): ?>
    <p class="ok">Un restaurant démo a été créé :</p>
    <ul style="margin:10px 0 18px 20px;line-height:1.9;">
      <li>Slug : <span class="k">foores</span></li>
      <li>Admin : <span class="k">admin@foores.ma</span></li>
      <li>Mot de passe : <span class="k">admin1234</span></li>
    </ul>
  <?php else: ?>
    <p>La base existe déjà, aucune donnée de démonstration n'a été ajoutée.</p>
  <?php endif; ?>

  <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap;">
    <a class="btn-primary" href="/admin/login.php">Accéder au dashboard</a>
    <a class="btn-ghost" href="/menu.php?r=foores">Voir le menu public</a>
  </div>
</div>
</body></html>

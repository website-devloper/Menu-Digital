<?php
// migrate_to_neon.php
// Migrates SQLite database data and schema to Neon PostgreSQL.
declare(strict_types=1);

require __DIR__ . '/shared/config.php';

$neonUrl = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? '');
if (empty($neonUrl)) {
    echo "ERROR: DATABASE_URL is not set. Please set DATABASE_URL in .env or environment.\n";
    echo "Example: postgresql://user:password@ep-xyz.neon.tech/dbname?sslmode=require\n";
    exit(1);
}

echo "1. Connecting to Neon PostgreSQL...\n";
$p = parse_url($neonUrl);
$host = $p['host'] ?? 'localhost';
$port = $p['port'] ?? 5432;
$user = $p['user'] ?? '';
$pass = isset($p['pass']) ? urldecode($p['pass']) : '';
$dbname = ltrim($p['path'] ?? '', '/');
$sslmode = 'require';
if (isset($p['query'])) {
    parse_str($p['query'], $q);
    if (!empty($q['sslmode'])) $sslmode = $q['sslmode'];
}

$endpoint = explode('.', $host)[0];
$endpoint = preg_replace('/-pooler$/', '', $endpoint);
$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode};options='endpoint={$endpoint}'";
try {
    $pg = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "ERROR connecting to Neon: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Connected successfully to Neon!\n";

echo "2. Creating tables in Neon PostgreSQL...\n";
$pg->exec("
CREATE TABLE IF NOT EXISTS restaurants (
  id          SERIAL PRIMARY KEY,
  slug        TEXT UNIQUE NOT NULL,
  name        TEXT NOT NULL,
  tagline     TEXT,
  logo        TEXT,
  currency    TEXT NOT NULL DEFAULT 'DH',
  phone       TEXT,
  hours       TEXT,
  address     TEXT,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id            SERIAL PRIMARY KEY,
  restaurant_id INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  email         TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  display_name  TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
  id            SERIAL PRIMARY KEY,
  restaurant_id INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  name          TEXT NOT NULL,
  position      INTEGER NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dishes (
  id            SERIAL PRIMARY KEY,
  restaurant_id INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  category_id   INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  name          TEXT NOT NULL,
  description   TEXT,
  price         REAL NOT NULL DEFAULT 0,
  photo         TEXT,
  available     INTEGER NOT NULL DEFAULT 1,
  position      INTEGER NOT NULL DEFAULT 0,
  flags         TEXT DEFAULT '',
  spice         INTEGER DEFAULT 0,
  is_special    INTEGER DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dish_photos (
  id       SERIAL PRIMARY KEY,
  dish_id  INTEGER NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
  photo    TEXT NOT NULL,
  position INTEGER NOT NULL DEFAULT 0,
  is_cover INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS reservations (
  id             SERIAL PRIMARY KEY,
  restaurant_id  INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  name           TEXT NOT NULL,
  phone          TEXT NOT NULL,
  email          TEXT,
  party_size     INTEGER NOT NULL,
  date           TEXT NOT NULL,
  time           TEXT NOT NULL,
  notes          TEXT,
  status         TEXT NOT NULL DEFAULT 'pending',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS promotions (
  id             SERIAL PRIMARY KEY,
  restaurant_id  INTEGER NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  name           TEXT NOT NULL,
  scope_type     TEXT NOT NULL DEFAULT 'all',
  scope_id       INTEGER,
  discount_pct   REAL NOT NULL DEFAULT 0,
  start_date     TEXT,
  end_date       TEXT,
  start_time     TEXT,
  end_time       TEXT,
  days_mask      INTEGER NOT NULL DEFAULT 127,
  active         INTEGER NOT NULL DEFAULT 1,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_cat_rest  ON categories(restaurant_id, position);
CREATE INDEX IF NOT EXISTS idx_dish_rest ON dishes(restaurant_id, category_id, position);
CREATE INDEX IF NOT EXISTS idx_photos_dish ON dish_photos(dish_id, position);
");
echo "Tables created or already exist.\n";

$sqlitePath = APP_ROOT . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'menu.db';
if (file_exists($sqlitePath)) {
    echo "3. Migrating data from SQLite ($sqlitePath) to Neon...\n";
    $sqlite = new PDO('sqlite:' . $sqlitePath);
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sqlite->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $tables = ['restaurants', 'users', 'categories', 'dishes', 'dish_photos', 'reservations', 'promotions'];
    foreach ($tables as $t) {
        $rows = $sqlite->query("SELECT * FROM $t")->fetchAll();
        if (empty($rows)) continue;

        echo " - Migrating " . count($rows) . " rows for table '$t'...\n";
        $cols = array_keys($rows[0]);
        $colList = implode(', ', $cols);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));

        $stmt = $pg->prepare("INSERT INTO $t ($colList) VALUES ($placeholders) ON CONFLICT (id) DO NOTHING");
        foreach ($rows as $row) {
            $stmt->execute(array_values($row));
        }

        // Sync sequence
        $seq = $pg->query("SELECT pg_get_serial_sequence('$t', 'id')")->fetchColumn();
        if ($seq) {
            $pg->exec("SELECT setval('$seq', COALESCE((SELECT MAX(id) FROM $t), 1), true)");
        }
    }
    echo "Data migration completed successfully!\n";
} else {
    echo "No SQLite database found to import. Initial schema is ready.\n";
}

echo "Neon migration complete!\n";

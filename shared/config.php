<?php
// Wevtex Digital Menu — shared config
// Keep this file the only place that knows paths, secrets and defaults.

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_ROOT',       dirname(__DIR__));
define('DATA_DIR',       APP_ROOT . DIRECTORY_SEPARATOR . 'data');
define('UPLOADS_DIR',    APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');
define('UPLOADS_URL',    '/uploads');
define('DB_PATH',        DATA_DIR . DIRECTORY_SEPARATOR . 'menu.db');
define('APP_NAME',       'Wevtex · Menu Digital');
define('CURRENCY',       'DH');

if (!is_dir(DATA_DIR))    { @mkdir(DATA_DIR, 0775, true); }
if (!is_dir(UPLOADS_DIR)) { @mkdir(UPLOADS_DIR, 0775, true); }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }
    return $pdo;
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(400);
        exit('Bad CSRF token.');
    }
}

function require_auth(): array {
    if (empty($_SESSION['uid'])) {
        header('Location: /admin/login.php');
        exit;
    }
    $st = db()->prepare('SELECT id, restaurant_id, email, display_name FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch();
    if (!$u) { session_destroy(); header('Location: /admin/login.php'); exit; }
    return $u;
}

function current_restaurant(array $user): array {
    $st = db()->prepare('SELECT * FROM restaurants WHERE id = ?');
    $st->execute([$user['restaurant_id']]);
    return $st->fetch() ?: [];
}

function restaurant_by_slug(string $slug): ?array {
    $st = db()->prepare('SELECT * FROM restaurants WHERE slug = ?');
    $st->execute([$slug]);
    $r = $st->fetch();
    return $r ?: null;
}

function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function money(float $v): string {
    return number_format($v, 2, ',', ' ') . ' ' . CURRENCY;
}

function slugify(string $s): string {
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $s));
    return trim($s, '-') ?: ('r-' . substr(bin2hex(random_bytes(3)), 0, 6));
}

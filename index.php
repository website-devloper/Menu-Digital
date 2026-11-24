<?php
require __DIR__ . '/shared/config.php';

try {
    $rows = db()->query('SELECT slug FROM restaurants ORDER BY id LIMIT 1')->fetchAll();
    if ($rows) { header('Location: /menu.php?r=' . rawurlencode($rows[0]['slug'])); exit; }
} catch (Throwable $e) {
    // DB not initialized yet
}
header('Location: /setup.php');
exit;

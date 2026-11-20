<?php
require __DIR__ . '/shared/config.php';

// If a single restaurant exists, land on its menu by default.
if (!file_exists(DB_PATH)) { header('Location: /setup.php'); exit; }

$rows = db()->query('SELECT slug FROM restaurants ORDER BY id LIMIT 1')->fetchAll();
if ($rows) { header('Location: /menu.php?r=' . rawurlencode($rows[0]['slug'])); exit; }
header('Location: /setup.php');

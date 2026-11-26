<?php
require __DIR__ . '/shared/config.php';
require __DIR__ . '/shared/lib.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    // If no ID provided, pick the first dish
    $st = db()->query("SELECT id FROM dishes ORDER BY position, id LIMIT 1");
    $id = $st ? (int)$st->fetchColumn() : 0;
}

$st = db()->prepare('
    SELECT d.*, c.name AS cat_name, c.id AS cat_id, r.name AS rest_name, r.slug AS rest_slug, r.currency, r.phone AS rest_phone
    FROM dishes d
    JOIN restaurants r ON r.id = d.restaurant_id
    LEFT JOIN categories c ON c.id = d.category_id
    WHERE d.id = ?');
$st->execute([$id]);
$d = $st->fetch();

if (!$d) {
    http_response_code(404);
    exit('Plat introuvable.');
}

$rid = (int)$d['restaurant_id'];
$CURRENCY = $d['currency'] ?: 'DH';

// Gallery photos of THIS SAME DISH
$g = db()->prepare('SELECT photo FROM dish_photos WHERE dish_id = ? ORDER BY position, id');
$g->execute([$id]);
$galleryRows = $g->fetchAll();
$gallery = !empty($galleryRows) ? array_column($galleryRows, 'photo') : [];
if (empty($gallery) && !empty($d['photo'])) {
    $gallery = [$d['photo']];
}
// Ensure at least 3 photos for carousel peek effect
if (count($gallery) === 1) {
    $gallery = [$gallery[0], $gallery[0], $gallery[0]];
} elseif (count($gallery) === 2) {
    $gallery[] = $gallery[0];
}

$heroPhoto = $gallery[0];
$prevPhoto = $gallery[count($gallery) - 1];
$nextPhoto = $gallery[1] ?? $gallery[0];

$backUrl = '/menu.php?r=' . rawurlencode($d['rest_slug']);
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($d['name']) ?> · <?= e($d['rest_name']) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#FAF7F2">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,500,0,0&display=swap" rel="stylesheet">

<style>
/* Material Symbols Rounded — Google Font icon system */
.msi, .material-symbols-rounded {
  font-family: 'Material Symbols Rounded' !important;
  font-weight: normal;
  font-style: normal;
  font-size: 22px;
  line-height: 1;
  letter-spacing: normal;
  text-transform: none;
  display: inline-block;
  white-space: nowrap;
  word-wrap: normal;
  direction: ltr;
  -webkit-font-feature-settings: 'liga';
  font-feature-settings: 'liga';
  -webkit-font-smoothing: antialiased;
  font-variation-settings: 'FILL' 0, 'wght' 500, 'GRAD' 0, 'opsz' 24;
  vertical-align: middle;
}
.msi.filled { font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24; }
.msi.sm { font-size: 18px; }
.msi.lg { font-size: 28px; }
.msi.xl { font-size: 36px; }

:root {
  --bg-canvas: #FAF7F2;
  --bg-card: #FFFFFF;
  --text-dark: #19201B;
  --text-muted: #7E7C77;
  --accent-terracotta: #B8532F;
  --accent-terracotta-hover: #A24625;
  --border-subtle: rgba(0, 0, 0, 0.08);
  --shadow-btn: 0 8px 24px rgba(184, 83, 47, 0.35);
  --font-serif: 'Playfair Display', Georgia, serif;
  --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
}

* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

html {
  overflow-x: clip;
}

body {
  font-family: var(--font-sans);
  background: var(--bg-canvas);
  color: var(--text-dark);
  line-height: 1.45;
  overflow-x: clip;
  padding-bottom: 120px;
}

.page-wrapper {
  max-width: 480px;
  margin: 0 auto;
  position: relative;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  animation: slideUpSheet .38s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUpSheet {
  from { transform: translateY(100%); opacity: 0.5; }
  to { transform: translateY(0); opacity: 1; }
}

/* Top App Bar */
.top-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 10px;
  background: transparent;
}

.nav-icon-btn {
  background: transparent;
  border: none;
  outline: none;
  cursor: pointer;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dark);
  transition: transform .15s ease;
  text-decoration: none;
}
.nav-icon-btn:active { transform: scale(0.9); }
.nav-icon-btn svg { width: 24px; height: 24px; stroke-width: 2.2; }

/* Hero Carousel with Peek Previews */
.hero-carousel-container {
  position: relative;
  width: 100%;
  padding: 10px 0 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  user-select: none;
  touch-action: pan-y;
}

/* Side Peek Images of the SAME dish */
.side-peek {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 72px;
  height: 210px;
  border-radius: 20px;
  overflow: hidden;
  opacity: 0.55;
  filter: blur(1.5px);
  z-index: 1;
  cursor: pointer;
  transition: transform .25s ease, opacity .25s ease, filter .25s ease;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}
.side-peek.left {
  left: -18px;
}
.side-peek.right {
  right: -18px;
}
.side-peek img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 20px;
  transition: opacity .25s ease;
}
.side-peek:hover {
  opacity: 0.85;
  filter: none;
}

/* Arrow Nav Buttons (Stay on same dish, switch photo) */
.peek-nav-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(0,0,0,0.06);
  color: var(--text-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 5;
  box-shadow: 0 4px 12px rgba(0,0,0,0.12);
  transition: transform .15s ease, background .15s ease;
  outline: none;
}
.peek-nav-btn.left-btn { left: 16px; }
.peek-nav-btn.right-btn { right: 16px; }
.peek-nav-btn:active { transform: translateY(-50%) scale(0.9); background: #FFF; }
.peek-nav-btn svg { width: 18px; height: 18px; stroke-width: 2.5; }

/* Main Central Dish Photo (Square/Card with rounded corners so it looks appetizing) */
.main-dish-visual {
  width: 290px;
  height: 250px;
  max-width: calc(100vw - 110px);
  border-radius: 26px;
  overflow: hidden;
  position: relative;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.16), 0 6px 14px rgba(0, 0, 0, 0.08);
  background: #FFF;
}
.main-dish-visual img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 26px;
  transition: opacity .22s ease, transform .22s ease;
}

/* Dots Indicator below image */
.carousel-dots-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 6px;
  margin-bottom: 20px;
}
.carousel-dot {
  cursor: pointer;
  transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
}
.carousel-dot.active {
  width: 24px;
  height: 5px;
  border-radius: 3px;
  background: #C45A35;
}
.carousel-dot.inactive {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #D9D2C7;
}

/* Dish Title & Price */
.dish-header-center {
  text-align: center;
  padding: 0 24px;
  margin-bottom: 24px;
}
.dish-title {
  font-size: 26px;
  font-weight: 800;
  color: var(--text-dark);
  letter-spacing: -0.01em;
  margin-bottom: 4px;
}
.dish-price {
  font-size: 22px;
  font-weight: 800;
  color: var(--accent-terracotta);
}
.dish-desc-text {
  font-size: 13.5px;
  color: var(--text-muted);
  max-width: 380px;
  margin: 8px auto 0;
  line-height: 1.45;
}

/* Customization Sections */
.custom-section {
  padding: 0 20px;
  margin-bottom: 20px;
}
.section-label {
  font-size: 15px;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 10px;
  display: block;
}

.options-pill-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* Active Cooking Pill (Moyenne) */
.pill-active-choice {
  background: var(--accent-terracotta);
  color: #FFFFFF;
  border: 1px solid var(--accent-terracotta);
  border-radius: 24px;
  padding: 9px 20px;
  font-family: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 3px 10px rgba(184,83,47,0.3);
  transition: transform .15s ease;
}
.pill-active-choice:active { transform: scale(0.96); }

/* Dropdown Choice Pills */
.pill-dropdown-choice {
  background: var(--bg-card);
  border: 1px solid var(--border-subtle);
  border-radius: 24px;
  padding: 8px 16px;
  font-family: inherit;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text-dark);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(0,0,0,0.03);
  transition: all .15s ease;
}
.pill-dropdown-choice:hover {
  border-color: #C45A35;
}
.pill-dropdown-choice:active {
  transform: scale(0.96);
}
.pill-dropdown-choice .emoji-icon {
  font-size: 16px;
}
.pill-dropdown-choice .chevron-icon {
  width: 14px;
  height: 14px;
  color: var(--text-muted);
}

/* Quantity Control Box */
.qty-selector-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin: 16px 0 24px;
}
.qty-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #FFF;
  border: 1px solid var(--border-subtle);
  font-size: 18px;
  font-weight: 800;
  color: var(--accent-terracotta);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.qty-btn:active { transform: scale(0.9); }
.qty-number {
  font-size: 18px;
  font-weight: 800;
  color: var(--text-dark);
  min-width: 24px;
  text-align: center;
}

/* Floating Bottom Button Bar */
.bottom-action-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: rgba(250, 247, 242, 0.98);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  padding: 12px 20px;
  padding-bottom: max(24px, calc(16px + env(safe-area-inset-bottom, 0px)));
  z-index: 50;
  border-top: 1px solid rgba(0,0,0,0.06);
  box-shadow: 0 -4px 16px rgba(0,0,0,0.04);
}
.bottom-action-bar .bar-inner {
  max-width: 440px;
  margin: 0 auto;
}

.btn-add-cart-large {
  width: 100%;
  height: 54px;
  min-height: 54px;
  border-radius: 28px;
  background: var(--accent-terracotta);
  color: #FFFFFF;
  border: none;
  outline: none;
  font-family: inherit;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: var(--shadow-btn);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: transform .15s ease, background .15s ease;
}
.btn-add-cart-large:active {
  transform: scale(0.98);
  background: var(--accent-terracotta-hover);
}

/* Toast Notice */
.toast-notice {
  position: fixed;
  top: 24px;
  left: 50%;
  transform: translateX(-50%) translateY(-60px);
  background: rgba(24, 32, 27, 0.95);
  color: #FFF;
  padding: 12px 24px;
  border-radius: 30px;
  font-size: 14px;
  font-weight: 600;
  z-index: 200;
  box-shadow: 0 10px 30px rgba(0,0,0,0.25);
  transition: transform .3s cubic-bezier(0.16, 1, 0.3, 1), opacity .3s ease;
  opacity: 0;
  pointer-events: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.toast-notice.show {
  transform: translateX(-50%) translateY(0);
  opacity: 1;
}
/* Customization Sheet Modal */
.cust-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 250;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity .28s ease;
}
.cust-modal-overlay.open {
  opacity: 1;
  pointer-events: auto;
}
.cust-sheet-panel {
  background: var(--bg-canvas);
  width: 100%;
  max-width: 500px;
  position: fixed;
  top: 58px;
  bottom: 0;
  left: 0;
  right: 0;
  margin: 0 auto;
  height: auto;
  max-height: calc(100% - 58px);
  border-radius: 28px 28px 0 0;
  display: flex;
  flex-direction: column;
  transform: translateY(105%);
  transition: transform .32s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 -10px 40px rgba(0,0,0,0.3);
  overflow: hidden;
}
.cust-modal-overlay.open .cust-sheet-panel {
  transform: translateY(0);
}
.sheet-drag-handle-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  padding-top: 12px;
  padding-bottom: 4px;
  cursor: pointer;
}
.sheet-drag-bar {
  width: 44px;
  height: 5px;
  border-radius: 3px;
  background: #D9D2C7;
}
.cust-header {
  padding: 14px 20px;
  border-bottom: 1px solid rgba(0,0,0,0.06);
  display: flex;
  align-items: center;
  gap: 12px;
  background: #FFF;
  flex-shrink: 0;
}
.cust-thumb {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  object-fit: cover;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.cust-header-info {
  flex: 1;
}
.cust-dish-name {
  font-family: var(--font-serif);
  font-size: 16.5px;
  font-weight: 700;
  color: var(--text-dark);
  line-height: 1.2;
}
.cust-dish-baseprice {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--accent-terracotta);
}
.cust-close-btn {
  background: #F4EFEB;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--text-dark);
}
.cust-close-btn svg { width: 18px; height: 18px; stroke-width: 2.2; }

/* Multi-Dish Customization Tabs */
.cust-item-tabs-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  background: #FFF;
  border-bottom: 1px solid rgba(0,0,0,0.06);
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  flex-shrink: 0;
}
.cust-item-tabs-list {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
}
.cust-tab-pill {
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all .2s ease;
  border: 1px solid #E2DACF;
  background: var(--bg-canvas);
  color: var(--text-dark);
}
.cust-tab-pill.active {
  background: var(--accent-terracotta);
  color: #FFF;
  border-color: var(--accent-terracotta);
  box-shadow: 0 2px 8px rgba(184,83,47,0.3);
}
.cust-tab-copy-btn {
  background: #F4EFEB;
  border: 1px solid #E2DACF;
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 11.5px;
  font-weight: 700;
  color: #6C5543;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.cust-tab-copy-btn:active {
  transform: scale(0.96);
}
.cust-current-dish-banner {
  background: #FFF7F0;
  border-left: 3px solid var(--accent-terracotta);
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 700;
  color: #8C401C;
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.cust-scroll-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding: 16px 20px 24px;
}
.cust-group {
  margin-bottom: 20px;
}
.cust-group-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 10px;
}
.cust-group-title {
  font-size: 14.5px;
  font-weight: 800;
  color: var(--text-dark);
}
.cust-badge-required {
  background: rgba(184,83,47,0.12);
  color: var(--accent-terracotta);
  font-size: 11px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 12px;
}
.cust-badge-optional {
  background: rgba(0,0,0,0.06);
  color: var(--text-muted);
  font-size: 11px;
  font-weight: 600;
  padding: 3px 9px;
  border-radius: 12px;
}
.cust-options-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.cust-opt-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 13px 16px;
  background: #FFF;
  border: 1.5px solid #EAE4DA;
  border-radius: 16px;
  cursor: pointer;
  transition: all .18s ease;
  user-select: none;
}
.cust-opt-card:active {
  transform: scale(0.99);
}
.cust-opt-card.selected {
  border-color: var(--accent-terracotta);
  background: #FFF9F5;
  box-shadow: 0 2px 10px rgba(184,83,47,0.1);
}
.cust-opt-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}
.cust-indicator {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid #D5CEBF;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all .18s ease;
  flex-shrink: 0;
}
.cust-indicator.checkbox {
  border-radius: 6px;
}
.cust-opt-card.selected .cust-indicator {
  border-color: var(--accent-terracotta);
  background: var(--accent-terracotta);
}
.cust-opt-card.selected .cust-indicator::after {
  content: '';
  width: 6px;
  height: 6px;
  background: #FFF;
  border-radius: 50%;
}
.cust-opt-card.selected .cust-indicator.checkbox::after {
  content: '✓';
  color: #FFF;
  font-size: 12px;
  font-weight: 800;
  background: transparent;
}
.cust-opt-label {
  font-size: 14.5px;
  font-weight: 600;
  color: var(--text-dark);
}
.cust-opt-price {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--accent-terracotta);
  flex-shrink: 0;
}
.cust-opt-price.free {
  color: var(--text-muted);
  font-weight: 500;
}

.cust-bottom-bar {
  flex-shrink: 0;
  background: #FFF;
  padding: 16px 24px;
  padding-bottom: max(26px, calc(16px + env(safe-area-inset-bottom, 0px)));
  border-top: 1px solid rgba(0,0,0,0.06);
  display: flex;
  flex-direction: column;
  gap: 14px;
  box-shadow: 0 -6px 20px rgba(0,0,0,0.05);
}
.cust-qty-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 4px 6px;
}
.cust-qty-row .qty-selector-wrap {
  margin: 0;
  gap: 12px;
  background: var(--bg-canvas);
  padding: 4px 6px;
  border-radius: 9999px;
  border: 1px solid #EAE4DA;
}
.cust-qty-row .qty-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #FFFFFF;
  border: 1px solid rgba(0,0,0,0.06);
  font-size: 16px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.06);
}
.cust-qty-row .qty-number {
  font-size: 16px;
  min-width: 20px;
}
</style>
</head>
<body>

<div class="page-wrapper">
  <!-- Top Bar -->
  <div class="top-nav">
    <a href="<?= e($backUrl) ?>" class="nav-icon-btn" title="Retour au menu">
      <span class="msi" aria-hidden="true">chevron_left</span>
    </a>

    <button class="nav-icon-btn" onclick="shareDish()" title="Partager">
      <span class="msi" aria-hidden="true">ios_share</span>
    </button>
  </div>

  <!-- Hero Carousel Container (Photos of the SAME dish) -->
  <div class="hero-carousel-container" id="carouselContainer">
    <!-- Left Peek Image of same dish -->
    <div class="side-peek left" onclick="changeDishImage('prev')" title="Photo précédente">
      <img src="/uploads/<?= e($prevPhoto) ?>" alt="Vue précédente" id="leftPeekImg">
    </div>

    <!-- Left Button Arrow (Switches image of same dish) -->
    <button class="peek-nav-btn left-btn" onclick="changeDishImage('prev')" title="Photo précédente">
      <span class="msi" aria-hidden="true">chevron_left</span>
    </button>

    <!-- Main Visual of the dish -->
    <div class="main-dish-visual">
      <img src="/uploads/<?= e($heroPhoto) ?>" alt="<?= e($d['name']) ?>" id="mainDishPhoto">
    </div>

    <!-- Right Button Arrow (Switches image of same dish) -->
    <button class="peek-nav-btn right-btn" onclick="changeDishImage('next')" title="Photo suivante">
      <span class="msi" aria-hidden="true">chevron_right</span>
    </button>

    <!-- Right Peek Image of same dish -->
    <div class="side-peek right" onclick="changeDishImage('next')" title="Photo suivante">
      <img src="/uploads/<?= e($nextPhoto) ?>" alt="Vue suivante" id="rightPeekImg">
    </div>
  </div>

  <!-- Dots Indicator below image -->
  <div class="carousel-dots-row" id="dotsContainer">
    <?php foreach ($gallery as $idx => $photo): ?>
      <span class="carousel-dot <?= $idx === 0 ? 'active' : 'inactive' ?>" onclick="changeDishImage(<?= $idx ?>)"></span>
    <?php endforeach; ?>
  </div>

  <!-- Dish Title & Price -->
  <div class="dish-header-center">
    <h1 class="dish-title" id="displayDishTitle"><?= e($d['name']) ?></h1>
    <div class="dish-price" id="displayDishPrice"><?= number_format((float)$d['price'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></div>
    <p class="dish-desc-text" id="displayDishDesc"><?= e($d['description'] ?? '') ?></p>
  </div>

  <!-- Quantity Selector -->
  <div class="qty-selector-wrap">
    <button class="qty-btn" onclick="adjustQty(-1)">-</button>
    <span class="qty-number" id="qtyDisplay">1</span>
    <button class="qty-btn" onclick="adjustQty(1)">+</button>
  </div>
</div>

<!-- Floating Bottom Add to Cart Bar -->
<div class="bottom-action-bar">
  <div class="bar-inner">
    <button class="btn-add-cart-large" onclick="handleAddToCart()">
      <span>Ajouter au panier</span>
      <span id="btnPriceTotal">• <?= number_format((float)$d['price'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></span>
    </button>
  </div>
</div>

<!-- Dish Customization Bottom Sheet Modal -->
<div class="cust-modal-overlay" id="customizationModalOverlay" onclick="closeCustomizationModal(event)">
  <div class="cust-sheet-panel" onclick="event.stopPropagation()">
    <div class="sheet-drag-handle-wrap" onclick="closeCustomizationModal()">
      <div class="sheet-drag-bar"></div>
    </div>
    
    <div class="cust-header">
      <img src="/uploads/<?= e($heroPhoto) ?>" id="custDishThumb" class="cust-thumb" alt="">
      <div class="cust-header-info">
        <div class="cust-dish-name"><?= e($d['name']) ?></div>
        <div class="cust-dish-baseprice"><?= number_format((float)$d['price'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></div>
      </div>
      <button class="cust-close-btn" onclick="closeCustomizationModal()">
        <span class="msi" aria-hidden="true">close</span>
      </button>
    </div>

    <!-- Multi-Item Tab Bar (Visible when qty > 1) -->
    <div class="cust-item-tabs-bar" id="custItemTabsBar" style="display:none;">
      <div class="cust-item-tabs-list" id="custTabsList">
        <!-- Dynamically rendered tabs -->
      </div>
      <button type="button" class="cust-tab-copy-btn" onclick="applyCustToAll()" title="Copier les options du plat actuel sur tous les autres">
        <span>📋 Idem pour tous</span>
      </button>
    </div>

    <div class="cust-scroll-body" id="custOptionsBody">
      <!-- Dynamically generated groups & options -->
    </div>

    <div class="cust-bottom-bar">
      <div class="cust-qty-row">
        <span style="font-size:13.5px; font-weight:700; color:var(--text-dark);">Quantité</span>
        <div class="qty-selector-wrap" style="margin:0;">
          <button class="qty-btn" onclick="adjustCustQty(-1)">-</button>
          <span class="qty-number" id="custQtyNumber">1</span>
          <button class="qty-btn" onclick="adjustCustQty(1)">+</button>
        </div>
      </div>
      <button class="btn-add-cart-large" onclick="confirmCustomizationAndAdd()">
        <span id="custBtnText">Confirmer & Ajouter</span>
        <span id="custBtnTotal">• <?= number_format((float)$d['price'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></span>
      </button>
    </div>
  </div>
</div>

<!-- Toast Notice -->
<div class="toast-notice" id="toastNotice">
  <span class="msi sm" style="color:#22C55E; font-variation-settings:'FILL' 1,'wght' 700,'GRAD' 0,'opsz' 24;" aria-hidden="true">check_circle</span>
  <span id="toastText">Ajouté au panier avec succès !</span>
</div>

<script>
const DISH_ID = <?= (int)$d['id'] ?>;
const DISH_NAME = '<?= addslashes(e($d['name'])) ?>';
const DISH_NAME_EN = '<?= addslashes(e($d['name_en'] ?? '')) ?>';
const DISH_NAME_AR = '<?= addslashes(e($d['name_ar'] ?? '')) ?>';
const DISH_DESC_EN = '<?= addslashes(e($d['description_en'] ?? '')) ?>';
const DISH_DESC_AR = '<?= addslashes(e($d['description_ar'] ?? '')) ?>';
const UNIT_PRICE = <?= (float)$d['price'] ?>;
const CURRENCY = '<?= e($CURRENCY) ?>';
const GALLERY = <?= json_encode($gallery) ?>;
const DISH_OPTIONS = <?= json_encode(!empty($d['options']) ? json_decode($d['options'], true) : []) ?>;

let currentLang = localStorage.getItem('maison_sola_lang') || 'fr';
if (currentLang === 'en') {
  if (DISH_NAME_EN) {
    const el = document.getElementById('displayDishTitle');
    if (el) el.innerText = DISH_NAME_EN;
  }
  if (DISH_DESC_EN) {
    const el = document.getElementById('displayDishDesc');
    if (el) el.innerText = DISH_DESC_EN;
  }
} else if (currentLang === 'ar') {
  document.documentElement.lang = 'ar';
  document.documentElement.dir = 'rtl';
  if (DISH_NAME_AR) {
    const el = document.getElementById('displayDishTitle');
    if (el) el.innerText = DISH_NAME_AR;
  }
  if (DISH_DESC_AR) {
    const el = document.getElementById('displayDishDesc');
    if (el) el.innerText = DISH_DESC_AR;
  }
}

let currentSlideIdx = 0;
let currentQty = 1;

// Customization State with Multi-Dish support
let custQty = 1;
let currentCustItemIdx = 0;
let custItems = [];

function createDefaultItemOptions() {
  const sel = {};
  if (DISH_OPTIONS && DISH_OPTIONS.length > 0) {
    DISH_OPTIONS.forEach((grp, gIdx) => {
      if (grp.type === 'single') {
        sel[gIdx] = [0];
      } else {
        sel[gIdx] = [];
      }
    });
  }
  return sel;
}

function openCustomizationModal() {
  custQty = Math.max(1, currentQty);
  currentCustItemIdx = 0;
  custItems = [];

  for (let i = 0; i < custQty; i++) {
    custItems.push(createDefaultItemOptions());
  }

  document.getElementById('custQtyNumber').innerText = custQty;
  updateTabsUI();
  renderCustomizationOptions();
  updateCustPrice();

  document.getElementById('customizationModalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeCustomizationModal(e) {
  document.getElementById('customizationModalOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

function updateTabsUI() {
  const bar = document.getElementById('custItemTabsBar');
  const tabsList = document.getElementById('custTabsList');

  if (custQty > 1) {
    bar.style.display = 'flex';
    let tabsHtml = '';
    for (let i = 0; i < custQty; i++) {
      const isActive = (i === currentCustItemIdx);
      tabsHtml += `
        <button type="button" class="cust-tab-pill ${isActive ? 'active' : ''}" onclick="switchCustItem(${i})">
          Plat #${i + 1}
        </button>
      `;
    }
    tabsList.innerHTML = tabsHtml;
  } else {
    bar.style.display = 'none';
  }
}

function switchCustItem(idx) {
  currentCustItemIdx = idx;
  updateTabsUI();
  renderCustomizationOptions();
  updateCustPrice();
}

function applyCustToAll() {
  if (custQty <= 1) return;
  const currentSel = JSON.parse(JSON.stringify(custItems[currentCustItemIdx]));
  for (let i = 0; i < custQty; i++) {
    custItems[i] = JSON.parse(JSON.stringify(currentSel));
  }
  const toast = document.getElementById('toastNotice');
  toast.innerText = 'Options copiées sur tous les plats !';
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 1600);
  updateCustPrice();
}

function adjustCustQty(delta) {
  const newQty = Math.max(1, custQty + delta);
  if (newQty === custQty) return;

  if (newQty > custQty) {
    const prevItem = custItems[custItems.length - 1];
    custItems.push(prevItem ? JSON.parse(JSON.stringify(prevItem)) : createDefaultItemOptions());
    currentCustItemIdx = newQty - 1;
  } else {
    custItems.pop();
    if (currentCustItemIdx >= newQty) {
      currentCustItemIdx = newQty - 1;
    }
  }

  custQty = newQty;
  currentQty = newQty;
  document.getElementById('qtyDisplay').innerText = currentQty;
  document.getElementById('custQtyNumber').innerText = custQty;

  updateTabsUI();
  renderCustomizationOptions();
  updateCustPrice();
}

function toggleCustOption(gIdx, iIdx) {
  const grp = DISH_OPTIONS[gIdx];
  if (!grp) return;

  const currentSel = custItems[currentCustItemIdx];
  if (!currentSel) return;

  if (grp.type === 'single') {
    currentSel[gIdx] = [iIdx];
  } else {
    if (!currentSel[gIdx]) currentSel[gIdx] = [];
    const pos = currentSel[gIdx].indexOf(iIdx);
    if (pos >= 0) {
      currentSel[gIdx].splice(pos, 1);
    } else {
      currentSel[gIdx].push(iIdx);
    }
  }

  renderCustomizationOptions();
  updateCustPrice();
}

function renderCustomizationOptions() {
  if (!DISH_OPTIONS) return;
  const container = document.getElementById('custOptionsBody');

  const bannerHtml = (custQty > 1) 
    ? `<div class="cust-current-dish-banner">
         <span>⚙️ Options pour : <b>Plat #${currentCustItemIdx + 1}</b> sur ${custQty}</span>
         <span style="font-size:11px; opacity:0.85;">Sélectionnez ci-dessous</span>
       </div>`
    : '';

  const currentSel = custItems[currentCustItemIdx] || {};
  let html = bannerHtml;

  DISH_OPTIONS.forEach((grp, gIdx) => {
    const isSingle = grp.type === 'single';
    const isRequired = grp.required;
    const grpTitle = (currentLang === 'en' && grp.name_en) ? grp.name_en : ((currentLang === 'ar' && grp.name_ar) ? grp.name_ar : grp.name);

    html += `
      <div class="cust-group" id="custGroup_${gIdx}">
        <div class="cust-group-head">
          <span class="cust-group-title">${grpTitle}</span>
          <span class="${isRequired ? 'cust-badge-required' : 'cust-badge-optional'}">
            ${badgeText}
          </span>
        </div>
        <div class="cust-options-list">
    `;

    grp.items.forEach((item, iIdx) => {
      const isSel = currentSel[gIdx] && currentSel[gIdx].includes(iIdx);
      const itemTitle = (currentLang === 'en' && item.name_en) ? item.name_en : ((currentLang === 'ar' && item.name_ar) ? item.name_ar : item.name);
      const priceText = item.price > 0 ? `+${item.price} ${CURRENCY}` : 'Inclus';
      const isFree = item.price === 0;

      html += `
        <div class="cust-opt-card ${isSel ? 'selected' : ''}" onclick="toggleCustOption(${gIdx}, ${iIdx})">
          <div class="cust-opt-left">
            <span class="cust-indicator ${isSingle ? 'radio' : 'checkbox'}"></span>
            <span class="cust-opt-label">${itemTitle}</span>
          </div>
          <span class="cust-opt-price ${isFree ? 'free' : ''}">${priceText}</span>
        </div>
      `;
    });

    html += `
        </div>
      </div>
    `;
  });

  container.innerHTML = html;
}

function calculateCustTotalPrice() {
  let total = 0;
  for (let k = 0; k < custQty; k++) {
    let itemPrice = UNIT_PRICE;
    const itemSel = custItems[k] || {};
    if (DISH_OPTIONS && DISH_OPTIONS.length > 0) {
      DISH_OPTIONS.forEach((grp, gIdx) => {
        const selIndices = itemSel[gIdx] || [];
        selIndices.forEach(idx => {
          const item = grp.items[idx];
          if (item && item.price) {
            itemPrice += Number(item.price);
          }
        });
      });
    }
    total += itemPrice;
  }
  return total;
}

function updateCustPrice() {
  const total = calculateCustTotalPrice();
  const label = custQty > 1 ? `Confirmer les ${custQty} plats` : 'Confirmer & Ajouter';
  document.getElementById('custBtnText').innerText = label;
  document.getElementById('custBtnTotal').innerText = `• ${total.toLocaleString('fr-FR')} ${CURRENCY}`;
}

function confirmCustomizationAndAdd() {
  if (DISH_OPTIONS && DISH_OPTIONS.length > 0) {
    for (let k = 0; k < custQty; k++) {
      const itemSel = custItems[k] || {};
      for (let gIdx = 0; gIdx < DISH_OPTIONS.length; gIdx++) {
        const grp = DISH_OPTIONS[gIdx];
        if (grp.required) {
          const sel = itemSel[gIdx] || [];
          if (sel.length === 0) {
            switchCustItem(k);
            const grpEl = document.getElementById(`custGroup_${gIdx}`);
            if (grpEl) grpEl.scrollIntoView({ behavior: 'smooth' });
            const toast = document.getElementById('toastNotice');
            toast.innerText = `Plat #${k + 1} : Veuillez choisir ${grp.name}`;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 2000);
            return;
          }
        }
      }
    }
  }

  let cart = JSON.parse(localStorage.getItem('maison_sola_cart') || '{}');

  for (let k = 0; k < custQty; k++) {
    const itemSel = custItems[k] || {};
    let itemUnitPrice = UNIT_PRICE;
    const choiceNames = [];
    const keyParts = [DISH_ID];

    if (DISH_OPTIONS && DISH_OPTIONS.length > 0) {
      DISH_OPTIONS.forEach((grp, gIdx) => {
        const sel = itemSel[gIdx] || [];
        sel.forEach(idx => {
          const it = grp.items[idx];
          if (it) {
            choiceNames.push(it.name + (it.price > 0 ? ` (+${it.price} ${CURRENCY})` : ''));
            keyParts.push(`${gIdx}:${idx}`);
            if (it.price) itemUnitPrice += Number(it.price);
          }
        });
      });
    }

    const cartKey = keyParts.join('_');
    const customSummary = choiceNames.join(', ');

    if (!cart[cartKey]) {
      cart[cartKey] = {
        cartKey: cartKey,
        id: DISH_ID,
        name: DISH_NAME,
        price: itemUnitPrice,
        photo: GALLERY[0],
        qty: 1,
        customSummary: customSummary
      };
    } else {
      cart[cartKey].qty += 1;
    }
  }

  localStorage.setItem('maison_sola_cart', JSON.stringify(cart));
  closeCustomizationModal();

  const toast = document.getElementById('toastNotice');
  toast.innerText = `${custQty}x ${DISH_NAME} personnalisé${custQty > 1 ? 's' : ''} ajouté${custQty > 1 ? 's' : ''} !`;
  toast.classList.add('show');
  
  if (navigator.vibrate) navigator.vibrate(50);

  setTimeout(() => {
    toast.classList.remove('show');
    window.location.href = '<?= e($backUrl) ?>';
  }, 900);
}

function handleAddToCart() {
  if (DISH_OPTIONS && DISH_OPTIONS.length > 0) {
    openCustomizationModal();
    return;
  }

  const cartKey = String(DISH_ID);
  let cart = JSON.parse(localStorage.getItem('maison_sola_cart') || '{}');
  if (!cart[cartKey]) {
    cart[cartKey] = {
      cartKey: cartKey,
      id: DISH_ID,
      name: DISH_NAME,
      price: UNIT_PRICE,
      photo: GALLERY[0],
      qty: currentQty,
      customSummary: ''
    };
  } else {
    cart[cartKey].qty += currentQty;
  }
  localStorage.setItem('maison_sola_cart', JSON.stringify(cart));

  const toast = document.getElementById('toastNotice');
  toast.innerText = `${currentQty}x ${DISH_NAME} ajouté au panier !`;
  toast.classList.add('show');
  
  if (navigator.vibrate) navigator.vibrate(50);

  setTimeout(() => {
    toast.classList.remove('show');
    window.location.href = '<?= e($backUrl) ?>';
  }, 900);
}

// Switch photos of THIS SAME DISH in place
function changeDishImage(dir) {
  if (GALLERY.length <= 1) return;

  if (dir === 'next') {
    currentSlideIdx = (currentSlideIdx + 1) % GALLERY.length;
  } else if (dir === 'prev') {
    currentSlideIdx = (currentSlideIdx - 1 + GALLERY.length) % GALLERY.length;
  } else if (typeof dir === 'number') {
    currentSlideIdx = dir;
  }

  const prevIdx = (currentSlideIdx - 1 + GALLERY.length) % GALLERY.length;
  const nextIdx = (currentSlideIdx + 1) % GALLERY.length;

  const mainImg = document.getElementById('mainDishPhoto');
  mainImg.style.opacity = '0';
  mainImg.style.transform = 'scale(0.92)';

  setTimeout(() => {
    mainImg.src = '/uploads/' + GALLERY[currentSlideIdx];
    mainImg.style.opacity = '1';
    mainImg.style.transform = 'scale(1)';
  }, 120);

  const leftPeek = document.getElementById('leftPeekImg');
  const rightPeek = document.getElementById('rightPeekImg');
  if (leftPeek) leftPeek.src = '/uploads/' + GALLERY[prevIdx];
  if (rightPeek) rightPeek.src = '/uploads/' + GALLERY[nextIdx];

  // Update dots
  const dots = document.querySelectorAll('.carousel-dot');
  dots.forEach((dot, idx) => {
    if (idx === currentSlideIdx) {
      dot.className = 'carousel-dot active';
    } else {
      dot.className = 'carousel-dot inactive';
    }
  });

  if (navigator.vibrate) navigator.vibrate(25);
}

// Touch swipe support on image
let touchStartX = 0;
let touchEndX = 0;
const carouselEl = document.getElementById('carouselContainer');
carouselEl.addEventListener('touchstart', e => {
  touchStartX = e.changedTouches[0].screenX;
}, { passive: true });
carouselEl.addEventListener('touchend', e => {
  touchEndX = e.changedTouches[0].screenX;
  if (touchStartX - touchEndX > 45) {
    changeDishImage('next');
  } else if (touchEndX - touchStartX > 45) {
    changeDishImage('prev');
  }
}, { passive: true });

function adjustQty(delta) {
  currentQty = Math.max(1, currentQty + delta);
  document.getElementById('qtyDisplay').innerText = currentQty;
  const total = (currentQty * UNIT_PRICE).toLocaleString('fr-FR');
  document.getElementById('btnPriceTotal').innerText = `• ${total} ${CURRENCY}`;
}





function shareDish() {
  if (navigator.share) {
    navigator.share({
      title: `${DISH_NAME} · Maison Sola`,
      url: window.location.href
    }).catch(() => {});
  } else {
    navigator.clipboard.writeText(window.location.href);
    const toast = document.getElementById('toastNotice');
    toast.innerText = 'Lien copié dans le presse-papier !';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2000);
  }
}
</script>

</body>
</html>

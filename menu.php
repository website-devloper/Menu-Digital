<?php
require __DIR__ . '/shared/config.php';
require __DIR__ . '/shared/lib.php';

$slug = $_GET['r'] ?? '';
$tableParam = trim((string)($_GET['t'] ?? ($_GET['table'] ?? '')));
$rest = null;
if (!empty($slug)) {
    $rest = restaurant_by_slug($slug);
}
if (!$rest) {
    // Fallback to first available restaurant in database
    $st = db()->query("SELECT * FROM restaurants ORDER BY id LIMIT 1");
    $rest = $st ? $st->fetch() : null;
}

if (!$rest) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><title>Restaurant introuvable</title>';
    echo '<div style="padding:60px 24px;text-align:center;font-family:sans-serif;">';
    echo '<h2>Restaurant introuvable</h2><p>Veuillez initialiser la base de données.</p></div>';
    exit;
}

$rid = (int)$rest['id'];

$cats = db()->prepare("SELECT * FROM categories WHERE restaurant_id = ? ORDER BY position, id");
$cats->execute([$rid]);
$categories = $cats->fetchAll();

$ds = db()->prepare("SELECT * FROM dishes WHERE restaurant_id = ? AND available = 1 ORDER BY position, id");
$ds->execute([$rid]);
$dishes = $ds->fetchAll();

$CURRENCY = $rest['currency'] ?: 'DH';

// Map photos for category circles
$catPhotos = [];
foreach ($categories as $cat) {
    if (!empty($cat['image'])) {
        $catPhotos[$cat['id']] = $cat['image'];
        continue;
    }
    foreach ($dishes as $d) {
        if ((int)$d['category_id'] === (int)$cat['id'] && !empty($d['photo'])) {
            $catPhotos[$cat['id']] = $d['photo'];
            break;
        }
    }
    if (empty($catPhotos[$cat['id']])) {
        $catPhotos[$cat['id']] = 'dish_12_v1.jpg';
    }
}

// Fetch 3 photos gallery per dish from Neon DB
$gRows = db()->query("SELECT dish_id, photo FROM dish_photos ORDER BY position, id")->fetchAll();
$dishPhotosMap = [];
foreach ($gRows as $gr) {
    $dishPhotosMap[(int)$gr['dish_id']][] = $gr['photo'];
}

$dishesDataMap = [];
foreach ($dishes as $d) {
    $dPhotos = $dishPhotosMap[(int)$d['id']] ?? [];
    if (empty($dPhotos)) {
        $dPhotos = [$d['photo'], $d['photo'], $d['photo']];
    } elseif (count($dPhotos) === 1) {
        $dPhotos = [$dPhotos[0], $dPhotos[0], $dPhotos[0]];
    } elseif (count($dPhotos) === 2) {
        $dPhotos[] = $dPhotos[0];
    }
    $optionsJson = !empty($d['options']) ? json_decode($d['options'], true) : [];
    $dishesDataMap[$d['id']] = [
        'id'             => (int)$d['id'],
        'name'           => $d['name'],
        'name_en'        => !empty($d['name_en']) ? $d['name_en'] : '',
        'name_ar'        => !empty($d['name_ar']) ? $d['name_ar'] : '',
        'price'          => (float)$d['price'],
        'description'    => $d['description'] ?: 'Préparé avec soin et des ingrédients frais du jour.',
        'description_en' => !empty($d['description_en']) ? $d['description_en'] : '',
        'description_ar' => !empty($d['description_ar']) ? $d['description_ar'] : '',
        'photo'          => $d['photo'],
        'photos'         => $dPhotos,
        'flags'          => (string)$d['flags'],
        'options'        => is_array($optionsJson) ? $optionsJson : []
    ];
}

$categoriesMap = [];
foreach ($categories as $c) {
    $categoriesMap[$c['id']] = [
        'id'      => (int)$c['id'],
        'name'    => $c['name'],
        'name_en' => !empty($c['name_en']) ? $c['name_en'] : '',
        'name_ar' => !empty($c['name_ar']) ? $c['name_ar'] : ''
    ];
}

$firstPhoto = !empty($dishes[0]['photo']) ? $dishes[0]['photo'] : 'dish_12_v1.jpg';
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($rest['name']) ?> · Menu Digital</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#FAF7F2">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
.msi.bold   { font-variation-settings: 'FILL' 0, 'wght' 700, 'GRAD' 0, 'opsz' 24; }
.msi.sm     { font-size: 18px; }
.msi.lg     { font-size: 28px; }
.msi.xl     { font-size: 36px; }

:root {
  --bg-canvas: #FAF7F2;
  --bg-card: #FFFFFF;
  --text-dark: #19201B;
  --text-muted: #7E7C77;
  --accent-terracotta: #B8532F;
  --accent-terracotta-hover: #A24625;
  --gold-badge-bg: #FDF3DF;
  --gold-badge-text: #B45309;
  --gold-badge-border: #F7DCAC;
  --new-badge-bg: #FFF0EB;
  --new-badge-text: #C2410C;
  --new-badge-border: #FDC7B5;
  --font-serif: 'Plus Jakarta Sans', var(--font-sans), sans-serif;
  --font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  --font-logo: 'Cinzel', serif;
  --shadow-card: 0 4px 18px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.02);
  --shadow-floating: 0 12px 36px rgba(0, 0, 0, 0.22);
  --radius-card: 20px;
}

* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

html {
  overflow-x: clip;
}

html { background: #141715; }
body {
  font-family: var(--font-sans);
  background: var(--bg-canvas);
  color: var(--text-dark);
  line-height: 1.45;
  overflow-x: clip;
  padding-bottom: 0;
}
.app-luxury-footer { padding-bottom: 130px; }

/* App Header */
.app-header {
  position: sticky;
  top: 0;
  z-index: 60;
  background: rgba(250, 247, 242, 0.96);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid rgba(0,0,0,0.05);
  padding: 10px 18px 12px;
  height: 58px;
}

.top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 680px;
  margin: 0 auto;
  height: 100%;
}

.lang-wrapper {
  position: relative;
}
.lang-selector {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--text-dark);
  cursor: pointer;
  padding: 6px 8px;
  border-radius: 20px;
  background: rgba(0,0,0,0.03);
  transition: background .15s ease;
}
.lang-selector:active { background: rgba(0,0,0,0.08); }
.lang-selector svg { width: 17px; height: 17px; stroke: currentColor; }
.lang-selector .chevron { font-size: 10px; color: var(--text-muted); transition: transform .2s ease; }
.lang-wrapper.open .lang-selector .chevron { transform: rotate(180deg); }

.lang-dropdown-menu {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  background: #FFF;
  border-radius: 18px;
  box-shadow: 0 10px 32px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.06);
  border: 1px solid rgba(0,0,0,0.08);
  padding: 6px;
  z-index: 1000;
  display: none;
  flex-direction: column;
  min-width: 155px;
  animation: fadeIn .18s ease;
}
.lang-dropdown-menu.show {
  display: flex;
}
html[dir="rtl"] .lang-dropdown-menu {
  left: auto;
  right: 0;
}
.lang-opt-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 12px;
  cursor: pointer;
  transition: all .15s ease;
  font-size: 13.5px;
  font-weight: 700;
  color: var(--text-dark);
}
.lang-opt-row:hover, .lang-opt-row.active {
  background: #FAF7F2;
  color: var(--accent-terracotta);
}
.lang-opt-row .flag { font-size: 16px; }
.lang-opt-row .l-code { margin-left: auto; font-size: 11px; color: var(--text-muted); font-weight: 600; }
html[dir="rtl"] .lang-opt-row .l-code { margin-left: 0; margin-right: auto; }

/* Right to Left (RTL) Arabic Styles */
html[dir="rtl"] {
  direction: rtl;
  text-align: right;
}
html[dir="rtl"] .dish-header-center,
html[dir="rtl"] .dish-title,
html[dir="rtl"] .dish-title-sheet {
  text-align: center;
}
html[dir="rtl"] .brand-center {
  direction: ltr;
}
html[dir="rtl"] .badge-card {
  left: auto;
  right: 10px;
}
html[dir="rtl"] .btn-add-plus {
  margin-left: 0;
  margin-right: auto;
}
html[dir="rtl"] .peek-nav-btn.left-btn {
  left: auto;
  right: 16px;
  transform: rotate(180deg);
}
html[dir="rtl"] .peek-nav-btn.right-btn {
  right: auto;
  left: 16px;
  transform: rotate(180deg);
}
html[dir="rtl"] .cust-current-dish-banner {
  border-left: none;
  border-right: 3px solid var(--accent-terracotta);
}

/* Center Brand */
.brand-center {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-decoration: none;
  color: inherit;
  padding: 4px 6px;
  transition: transform .15s, opacity .15s;
}
.brand-center:active {
  transform: scale(0.96);
  opacity: 0.85;
}
.brand-logo-img {
  height: 27px;
  width: auto;
  display: block;
  object-fit: contain;
}
.brand-wevtex-logo {
  font-family: 'Plus Jakarta Sans', var(--font-sans), sans-serif;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: #19201B;
  line-height: 1;
  text-transform: lowercase;
}

/* Top Right Actions */
.top-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}
.icon-btn {
  background: transparent;
  border: none;
  outline: none;
  cursor: pointer;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dark);
  position: relative;
  transition: transform .15s ease;
}
.icon-btn:active { transform: scale(0.92); }
.icon-btn svg { width: 22px; height: 22px; stroke-width: 1.8; }
.badge-count {
  position: absolute;
  top: 1px;
  right: 0px;
  background: var(--accent-terracotta);
  color: #FFF;
  font-size: 10px;
  font-weight: 800;
  min-width: 17px;
  height: 17px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  box-shadow: 0 2px 5px rgba(184,83,47,0.4);
}

/* App Main Container */
.main-wrapper {
  max-width: 680px;
  margin: 0 auto;
  padding: 18px 16px;
}

/* Section Header */
.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}
.section-title {
  font-family: 'Plus Jakarta Sans', var(--font-sans), sans-serif;
  font-size: 26px;
  font-weight: 900;
  color: var(--text-dark);
  letter-spacing: -0.03em;
}

.pill-popular {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: linear-gradient(135deg, #FDF0D5, #F9DDA4);
  color: #92530A;
  font-size: 12px;
  font-weight: 700;
  padding: 5px 12px;
  border-radius: 20px;
  border: 1px solid rgba(229, 186, 115, 0.4);
  box-shadow: 0 2px 6px rgba(229, 186, 115, 0.2);
}

/* Sticky Category Icons Bar (Pins under Navbar at top: 58px) */
.specialties-sticky-container {
  position: sticky;
  top: 58px;
  z-index: 55;
  background: rgba(250, 247, 242, 0.98);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  margin: 0 -16px 16px;
  padding: 6px 16px 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

/* Stories / Specialites Horizontal Row */
.specialties-carousel {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  overflow-x: auto;
  padding: 2px 2px 4px;
  margin: 0;
  scroll-behavior: smooth;
  -webkit-overflow-scrolling: touch;
}
.specialties-carousel::-webkit-scrollbar { display: none; }

.spec-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  flex-shrink: 0;
  text-decoration: none;
  outline: none;
}
.spec-ring {
  width: 74px;
  height: 74px;
  border-radius: 50%;
  padding: 2.5px;
  background: linear-gradient(135deg, #E5BA73, #D4A373, #B8532F);
  box-shadow: 0 4px 12px rgba(184, 83, 47, 0.16);
  transition: transform .2s ease;
}
.spec-item:active .spec-ring { transform: scale(0.95); }

.spec-inner {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  overflow: hidden;
  background: #FFF;
  border: 2px solid #FFF;
}
.spec-inner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.spec-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-dark);
  text-align: center;
  transition: all .2s ease;
  white-space: nowrap;
}
.spec-item.active .spec-label {
  background: var(--accent-terracotta);
  color: #FFF;
  padding: 3px 12px;
  border-radius: 14px;
  font-weight: 700;
  box-shadow: 0 3px 8px rgba(184,83,47,0.3);
}

/* Search Bar (Inline Filter) */
.search-box-wrap {
  margin-bottom: 22px;
  position: relative;
}
.search-input {
  width: 100%;
  background: #FFF;
  border: 1px solid rgba(0,0,0,0.06);
  border-radius: 16px;
  padding: 12px 16px 12px 42px;
  font-family: inherit;
  font-size: 14px;
  color: var(--text-dark);
  box-shadow: var(--shadow-card);
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.search-input:focus {
  border-color: var(--accent-terracotta);
  box-shadow: 0 0 0 3px rgba(184,83,47,0.12);
}
.search-box-wrap svg {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  color: #9C9A94;
}

/* Dishes Grid (2 columns) */
.dishes-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
  margin-top: 6px;
}

.dish-card {
  background: var(--bg-card);
  border-radius: 22px;
  overflow: hidden;
  border: 1px solid rgba(42,19,36,.06);
  box-shadow: 0 1px 2px rgba(42,19,36,.04), 0 10px 28px rgba(42,19,36,.08);
  display: flex;
  flex-direction: column;
  position: relative;
  transition: transform .35s cubic-bezier(.4,0,.2,1), box-shadow .35s cubic-bezier(.4,0,.2,1), border-color .35s ease;
  cursor: pointer;
}
.dish-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 4px 14px rgba(42,19,36,.08), 0 24px 48px rgba(42,19,36,.16);
  border-color: rgba(42,19,36,.14);
}
.dish-card:active { transform: translateY(-2px) scale(0.99); }
.dish-card .dish-card-img-wrap img { transition: transform .55s cubic-bezier(.4,0,.2,1); }
.dish-card:hover .dish-card-img-wrap img { transform: scale(1.06); }

/* Image Container */
.dish-card-img-wrap {
  width: 100%;
  aspect-ratio: 1 / 0.88;
  position: relative;
  overflow: hidden;
  background: #EDE8DE;
}
.dish-card-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* Badges */
.card-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  z-index: 2;
  font-size: 10.5px;
  font-weight: 900;
  letter-spacing: .04em;
  padding: 4px 10px;
  border-radius: 999px;
  box-shadow: 0 2px 8px rgba(0,0,0,.14);
  display: inline-flex;
  align-items: center;
  gap: 3px;
  backdrop-filter: blur(8px);
}
.card-badge.bestseller {
  background: rgba(254, 243, 199, 0.94);
  color: var(--gold-badge-text);
  border: 1px solid var(--gold-badge-border);
}
.card-badge.nouveau {
  background: rgba(255, 237, 230, 0.94);
  color: var(--new-badge-text);
  border: 1px solid var(--new-badge-border);
}
.card-badge.populaire {
  background: rgba(254, 240, 215, 0.94);
  color: #9A5B0B;
  border: 1px solid #FCDAA0;
}

/* Dots Carousel Indicator */
.card-dots {
  position: absolute;
  bottom: 8px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  align-items: center;
  gap: 4px;
  z-index: 2;
}
.card-dot {
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.5);
}
.card-dot.active {
  width: 14px;
  border-radius: 4px;
  background: #FFFFFF;
}

/* Card Body */
.dish-card-body {
  padding: 12px 12px 14px;
  display: flex;
  flex-direction: column;
  flex: 1;
  justify-content: space-between;
}
.dish-name {
  font-size: 16px;
  font-weight: 900;
  color: var(--text-dark);
  margin-bottom: 3px;
  line-height: 1.22;
  letter-spacing: -.02em;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.dish-short-desc {
  font-size: 12px;
  font-weight: 500;
  color: var(--text-muted);
  line-height: 1.4;
  margin-bottom: 10px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Price & Plus Button Row */
.dish-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
}
.dish-price {
  font-size: 17px;
  font-weight: 900;
  color: var(--accent-terracotta);
  letter-spacing: -.01em;
}
.btn-add-plus {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--accent-terracotta);
  color: #FFF;
  border: none;
  outline: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 6px 14px rgba(184,83,47,0.36), inset 0 1px 0 rgba(255,255,255,.2);
  transition: transform .2s ease, background .2s ease, box-shadow .2s ease;
}
.btn-add-plus:hover {
  transform: scale(1.08) rotate(90deg);
  box-shadow: 0 8px 20px rgba(184,83,47,0.48), inset 0 1px 0 rgba(255,255,255,.3);
}
.btn-add-plus:active {
  transform: scale(0.92);
  background: var(--accent-terracotta-hover);
}
.btn-add-plus .msi { font-size: 20px; font-variation-settings: 'FILL' 0, 'wght' 700, 'GRAD' 0, 'opsz' 24; }
.btn-add-plus svg { width: 18px; height: 18px; stroke-width: 2.5; }

/* Floating Bottom Bar */
.floating-bottom-bar {
  position: fixed;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  width: calc(100% - 32px);
  max-width: 540px;
  z-index: 50;
  background: rgba(30, 30, 30, 0.65);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-radius: 40px;
  padding: 6px 8px 6px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: var(--shadow-floating);
  border: 1px solid rgba(255, 255, 255, 0.12);
  transition: transform .3s ease, opacity .3s ease;
}
.floating-bottom-bar.hidden {
  transform: translate(-50%, 100px);
  opacity: 0;
  pointer-events: none;
}
.order-summary-txt {
  color: #FFFFFF;
  font-size: 13.5px;
  font-weight: 600;
  display: flex;
  flex-direction: column;
}
.order-summary-txt .sub-total {
  font-size: 11px;
  color: rgba(255,255,255,0.7);
  font-weight: 500;
}
.btn-view-cart {
  background: var(--accent-terracotta);
  color: #FFFFFF;
  border: none;
  outline: none;
  border-radius: 30px;
  padding: 10px 18px;
  font-family: inherit;
  font-size: 13.5px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(184,83,47,0.45);
  transition: transform .15s ease, background .15s ease;
}
.btn-view-cart:active { transform: scale(0.96); }
.btn-view-cart svg { width: 17px; height: 17px; }

/* ========================================================
   SLIDE-UP DISH DETAIL BOTTOM SHEET (Pulls up right under Navbar!)
   ======================================================== */
.dish-sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 100;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity .32s ease;
}
.dish-sheet-overlay.open {
  opacity: 1;
  pointer-events: auto;
}

.dish-sheet-panel {
  width: 100%;
  max-width: 500px;
  /* STOPS RIGHT UNDER THE NAVBAR! */
  position: fixed;
  top: 58px;
  bottom: 0;
  left: 0;
  right: 0;
  margin: 0 auto;
  height: auto;
  max-height: calc(100% - 58px);
  background: var(--bg-canvas);
  border-radius: 28px 28px 0 0;
  box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.28);
  display: flex;
  flex-direction: column;
  transform: translateY(105%);
  transition: transform .38s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
  border-top: 1px solid rgba(255, 255, 255, 0.4);
}
.dish-sheet-overlay.open .dish-sheet-panel {
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

.sheet-top-nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 20px 10px;
}
.sheet-icon-btn {
  background: transparent;
  border: none;
  outline: none;
  cursor: pointer;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-dark);
}
.sheet-icon-btn svg { width: 22px; height: 22px; stroke-width: 2.2; }

/* Scrollable Inner Body */
.sheet-scroll-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 20px;
}

/* 3-Photo Carousel inside Sheet (Of the SAME dish) */
.hero-carousel-container {
  position: relative;
  width: 100%;
  padding: 6px 0 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  user-select: none;
}
.side-peek {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 70px;
  height: 195px;
  border-radius: 20px;
  overflow: hidden;
  opacity: 0.55;
  filter: blur(1.5px);
  z-index: 1;
  cursor: pointer;
  transition: transform .25s ease, opacity .25s ease, filter .25s ease;
  box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}
.side-peek.left { left: -18px; }
.side-peek.right { right: -18px; }
.side-peek img { width: 100%; height: 100%; object-fit: cover; }

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
}
.peek-nav-btn.left-btn { left: 16px; }
.peek-nav-btn.right-btn { right: 16px; }
.peek-nav-btn svg { width: 18px; height: 18px; stroke-width: 2.5; }

.main-dish-visual {
  width: 280px;
  height: 220px;
  max-width: calc(100vw - 110px);
  border-radius: 24px;
  overflow: hidden;
  position: relative;
  z-index: 3;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.14), 0 4px 12px rgba(0,0,0,0.08);
  background: #FFF;
}
.main-dish-visual img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 24px;
  transition: opacity .2s ease, transform .2s ease;
}

/* Dots Indicator */
.carousel-dots-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 6px;
  margin-bottom: 18px;
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

.dish-header-center {
  text-align: center;
  padding: 0 24px;
  margin-bottom: 20px;
}
.dish-title-sheet {
  font-size: 25px;
  font-weight: 800;
  color: var(--text-dark);
  letter-spacing: -0.01em;
  margin-bottom: 4px;
}
.dish-price-sheet {
  font-size: 21px;
  font-weight: 800;
  color: var(--accent-terracotta);
}
.dish-desc-sheet {
  font-size: 13px;
  color: var(--text-muted);
  max-width: 380px;
  margin: 6px auto 0;
  line-height: 1.45;
}

/* Customization Pills */
.custom-section {
  padding: 0 20px;
  margin-bottom: 18px;
}
.section-label {
  font-size: 14.5px;
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
.pill-active-choice {
  background: var(--accent-terracotta);
  color: #FFFFFF;
  border: 1px solid var(--accent-terracotta);
  border-radius: 24px;
  padding: 8px 18px;
  font-family: inherit;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 3px 10px rgba(184,83,47,0.3);
}
.pill-dropdown-choice {
  background: var(--bg-card);
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 24px;
  padding: 8px 16px;
  font-family: inherit;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-dark);
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(0,0,0,0.03);
}
.pill-dropdown-choice .emoji-icon { font-size: 16px; }
.pill-dropdown-choice .chevron-icon { width: 14px; height: 14px; color: var(--text-muted); }

/* Quantity */
.qty-selector-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  margin: 14px 0 20px;
}
.qty-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #FFF;
  border: 1px solid rgba(0,0,0,0.08);
  font-size: 18px;
  font-weight: 800;
  color: var(--accent-terracotta);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.qty-number {
  font-size: 18px;
  font-weight: 800;
  color: var(--text-dark);
  min-width: 24px;
  text-align: center;
}

/* Sheet Sticky Bottom Bar */
.sheet-bottom-bar {
  flex-shrink: 0;
  position: relative;
  background: rgba(250, 247, 242, 0.98);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  padding: 12px 20px;
  padding-bottom: max(24px, calc(14px + env(safe-area-inset-bottom, 0px)));
  border-top: 1px solid rgba(0,0,0,0.06);
  z-index: 10;
  box-shadow: 0 -4px 16px rgba(0,0,0,0.04);
}
.btn-add-cart-large {
  width: 100%;
  height: 52px;
  min-height: 52px;
  border-radius: 28px;
  background: var(--accent-terracotta);
  color: #FFFFFF;
  border: none;
  outline: none;
  font-family: inherit;
  font-size: 15.5px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 8px 24px rgba(184, 83, 47, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.btn-add-cart-large:active { transform: scale(0.98); background: var(--accent-terracotta-hover); }

/* Cart Modal Bottom Sheet */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.55);
  backdrop-filter: blur(6px);
  z-index: 200;
  display: none;
  align-items: flex-end;
  justify-content: center;
}
.modal-overlay.open { display: flex; animation: fadeIn .25s ease; }

/* Luxury Cart Bottom Sheet (Clean Ivory Background with White Cards) */
.cart-bottom-sheet {
  background: var(--bg-canvas);
  width: 100%;
  max-width: 540px;
  border-radius: 32px 32px 0 0;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  box-shadow: 0 -10px 40px rgba(0,0,0,0.22);
  animation: slideUp .32s cubic-bezier(0.16, 1, 0.3, 1);
}

.cart-sheet-top-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 12px;
  position: relative;
  background: var(--bg-canvas);
  border-bottom: 1px solid rgba(0,0,0,0.04);
  flex-shrink: 0;
}

.cart-nav-icon-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: none;
  background: transparent;
  color: var(--text-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background .15s, transform .15s;
}
.cart-nav-icon-btn:active {
  transform: scale(0.92);
  background: rgba(0,0,0,0.06);
}
.cart-nav-icon-btn svg {
  width: 22px;
  height: 22px;
}

.cart-sheet-heading {
  font-family: var(--font-serif);
  font-size: 22px;
  font-weight: 700;
  color: var(--text-dark);
  letter-spacing: 0.02em;
  margin: 0;
}

.cart-sheet-scroll-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding: 18px 20px 36px;
}

/* Luxury Dish Item Card */
.cart-item-card {
  background: #FFFFFF;
  border-radius: 20px;
  padding: 12px 14px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 14px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.035);
  border: 1px solid rgba(0,0,0,0.04);
}

.cart-item-thumb {
  width: 68px;
  height: 68px;
  border-radius: 14px;
  object-fit: cover;
  background: #EDE8DE;
  flex-shrink: 0;
}

.cart-item-details {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.cart-item-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--text-dark);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cart-item-sub {
  font-size: 13px;
  color: #8C827A;
  margin-top: 2px;
  line-height: 1.35;
}

.cart-item-price-val {
  font-size: 16px;
  font-weight: 800;
  color: var(--accent-terracotta);
  margin-top: 5px;
}

.cart-item-controls {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-shrink: 0;
}

.cart-item-qty-badge {
  font-size: 14.5px;
  font-weight: 600;
  color: var(--text-dark);
}

.cart-item-stepper {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--bg-canvas);
  padding: 3px 5px;
  border-radius: 9999px;
  border: 1px solid rgba(0,0,0,0.06);
}

.cart-btn-step {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: none;
  font-size: 16px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform .1s;
}
.cart-btn-step:active {
  transform: scale(0.9);
}
.cart-btn-step.minus {
  background: #EFE9DF;
  color: var(--text-dark);
}
.cart-btn-step.plus {
  background: var(--accent-terracotta);
  color: #FFFFFF;
}

.cart-step-quantity {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--text-dark);
  min-width: 14px;
  text-align: center;
}

/* Total Row */
.cart-total-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 4px 14px;
  margin-top: 6px;
}

.cart-total-title-txt {
  font-size: 22px;
  font-weight: 700;
  color: var(--text-dark);
}

.cart-total-sum-txt {
  font-size: 24px;
  font-weight: 800;
  color: var(--text-dark);
  letter-spacing: -0.02em;
}

/* Vos Informations Section */
.cart-user-info-section {
  margin-top: 10px;
}

.cart-user-info-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--text-dark);
  margin-bottom: 14px;
  font-family: var(--font-sans);
}

.cart-form-group {
  margin-bottom: 14px;
}

.cart-form-label {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text-dark);
  margin-bottom: 6px;
  display: block;
}

.cart-clean-input {
  width: 100%;
  background: #FFFFFF;
  border: 1px solid #E6DFD5;
  border-radius: 14px;
  padding: 14px 18px;
  font-size: 15px;
  color: var(--text-dark);
  box-shadow: 0 1px 4px rgba(0,0,0,0.02);
  outline: none;
  transition: border-color .15s, box-shadow .15s;
  font-family: inherit;
}
.cart-clean-input:focus {
  border-color: var(--accent-terracotta);
  box-shadow: 0 0 0 3px rgba(184,83,47,0.15);
}

.cart-confirm-cta-btn {
  width: 100%;
  background: var(--accent-terracotta);
  color: #FFFFFF;
  font-size: 16.5px;
  font-weight: 700;
  padding: 16px;
  border-radius: 9999px;
  border: none;
  cursor: pointer;
  box-shadow: 0 4px 18px rgba(184,83,47,0.35);
  margin-top: 18px;
  margin-bottom: 12px;
  transition: transform .15s, background .15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.cart-confirm-cta-btn:active {
  transform: scale(0.98);
  background: var(--accent-terracotta-hover);
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
.qty-control { display: inline-flex; align-items: center; gap: 10px; background: var(--bg-canvas); padding: 4px 10px; border-radius: 20px; }
.cart-total-box { background: var(--bg-canvas); padding: 16px; border-radius: 16px; margin: 18px 0; display: flex; justify-content: space-between; align-items: center; }
.cart-total-label { font-size: 15px; font-weight: 600; }
.cart-total-val { font-size: 20px; font-weight: 800; color: var(--accent-terracotta); }
.checkout-btn { width: 100%; background: var(--accent-terracotta); color: #FFF; border: none; border-radius: 16px; padding: 15px; font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 16px rgba(184,83,47,0.35); }

/* Toast */
.toast-notice {
  position: fixed;
  top: 70px;
  left: 50%;
  transform: translateX(-50%) translateY(-30px);
  background: rgba(24, 32, 27, 0.95);
  color: #FFF;
  padding: 12px 24px;
  border-radius: 30px;
  font-size: 14px;
  font-weight: 600;
  z-index: 300;
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

@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Luxury Footer */
.app-luxury-footer {
  background: #141715;
  color: #EDE8DE;
  margin-top: 36px;
  padding: 44px 20px 120px;
  border-top: 1px solid rgba(212, 175, 55, 0.22);
  position: relative;
  overflow: hidden;
}
.app-luxury-footer::before {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 240px;
  height: 2px;
  background: linear-gradient(90deg, transparent, #B8532F, #D4AF37, #B8532F, transparent);
}
.footer-container {
  max-width: 680px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 24px;
}
.footer-brand-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.footer-emblem-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 10px 18px;
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.015));
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: 0 10px 30px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.08);
  margin-bottom: 4px;
}
.footer-wevtex-logo {
  height: 44px;
  max-height: 44px;
  max-width: 180px;
  width: auto;
  display: block;
  object-fit: contain;
  filter: drop-shadow(0 2px 8px rgba(0,0,0,.45));
}
.footer-brand-title {
  font-family: var(--font-sans);
  font-size: 26px;
  font-weight: 900;
  letter-spacing: -0.02em;
  text-transform: none;
  color: #FAF7F2;
  margin: 0;
}
.footer-tagline {
  font-size: 13.5px;
  color: rgba(237, 232, 222, 0.7);
  letter-spacing: 0.02em;
  margin: 0;
  font-weight: 500;
}
.footer-ornament {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #D4AF37;
  font-size: 11px;
  margin-top: 6px;
  opacity: 0.9;
}
.footer-ornament::before,
.footer-ornament::after {
  content: "";
  width: 36px;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(212,175,55,.6), transparent);
}
.footer-info-grid {
  display: flex;
  flex-direction: column;
  gap: 14px;
  width: 100%;
  max-width: 460px;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.045), rgba(255,255,255,0.015));
  padding: 20px 22px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 12px 40px rgba(0,0,0,.3), inset 0 1px 0 rgba(255,255,255,.06);
}
.footer-info-item {
  display: flex;
  align-items: center;
  gap: 14px;
  text-align: left;
}
[dir="rtl"] .footer-info-item {
  text-align: right;
}
.footer-info-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, rgba(241,172,24,.18), rgba(184, 83, 47, 0.18));
  color: #F1AC18;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid rgba(241,172,24,0.3);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
}
.footer-info-icon .msi {
  font-size: 20px;
  font-variation-settings: 'FILL' 1, 'wght' 600, 'GRAD' 0, 'opsz' 24;
}
.footer-info-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.footer-info-label {
  font-size: 10.5px;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: rgba(241, 172, 24, 0.75);
  font-weight: 800;
}
.footer-info-val {
  font-size: 14px;
  color: #FAF7F2;
  font-weight: 700;
  letter-spacing: -.005em;
  text-decoration: none;
}
.footer-phone-link {
  color: #FAF7F2;
  transition: color .2s;
}
.footer-phone-link:hover {
  color: var(--accent-terracotta);
}
.footer-social-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  width: 100%;
}
.footer-social-title {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: rgba(237, 232, 222, 0.65);
  font-weight: 600;
}
.footer-social-pills {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.footer-social-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.055);
  color: #FAF7F2;
  text-decoration: none;
  font-size: 13px;
  font-weight: 700;
  letter-spacing: -.005em;
  border: 1px solid rgba(255, 255, 255, 0.12);
  transition: all .25s cubic-bezier(.4,0,.2,1);
}
.footer-social-btn .brand-ico {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  transition: transform .25s ease;
}
.footer-social-btn:hover { transform: translateY(-2px); color: #FFF; }
.footer-social-btn:hover .brand-ico { transform: scale(1.15); }
.footer-social-btn.ig-btn:hover {
  background: linear-gradient(45deg, #F58529, #DD2A7B, #8134AF);
  border-color: transparent;
  box-shadow: 0 6px 18px rgba(221,42,123,0.42);
}
.footer-social-btn.whatsapp-btn:hover {
  background: #25D366;
  border-color: #25D366;
  box-shadow: 0 6px 18px rgba(37,211,102,0.42);
}
.footer-social-btn.tt-btn:hover {
  background: #000;
  border-color: #000;
  box-shadow: 0 6px 18px rgba(0,0,0,0.5);
}
.footer-social-btn.fb-btn:hover {
  background: #1877F2;
  border-color: #1877F2;
  box-shadow: 0 6px 18px rgba(24,119,242,0.42);
}
.footer-social-btn.maps-btn:hover {
  background: #4285F4;
  border-color: #4285F4;
  box-shadow: 0 6px 18px rgba(66,133,244,0.42);
}
.footer-badges-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.footer-badge {
  font-size: 11.5px;
  color: #D4AF37;
  letter-spacing: 0.04em;
  background: rgba(212, 175, 55, 0.08);
  border: 1px solid rgba(212, 175, 55, 0.22);
  padding: 4px 12px;
  border-radius: 20px;
}
.footer-divider-line {
  width: 100%;
  max-width: 480px;
  height: 1px;
  background: rgba(255, 255, 255, 0.08);
  margin: 4px 0;
}
.footer-bottom-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: center;
}
.footer-copy {
  font-size: 12px;
  color: rgba(237, 232, 222, 0.45);
  margin: 0;
}
.footer-powered {
  font-size: 11.5px;
  color: rgba(237, 232, 222, 0.45);
  display: flex;
  align-items: center;
  gap: 4px;
}
</style>
</head>
<body>

<!-- App Sticky Header (Remains visible at top!) -->
<header class="app-header">
  <div class="top-bar">
    <!-- Language Toggle with Dropdown -->
    <div class="lang-wrapper" id="langWrapper">
      <div class="lang-selector" onclick="toggleLangMenu(event)">
        <span class="msi sm" aria-hidden="true">language</span>
        <span id="currentLangLabel">FR</span>
        <span class="msi sm chevron" aria-hidden="true">expand_more</span>
      </div>

      <div class="lang-dropdown-menu" id="langDropdownMenu">
        <div class="lang-opt-row" onclick="setLanguage('fr')">
          <span class="flag">🇫🇷</span>
          <span>Français</span>
          <span class="l-code">FR</span>
        </div>
        <div class="lang-opt-row" onclick="setLanguage('en')">
          <span class="flag">🇬🇧</span>
          <span>English</span>
          <span class="l-code">EN</span>
        </div>
        <div class="lang-opt-row" onclick="setLanguage('ar')">
          <span class="flag">🇲🇦</span>
          <span>العربية</span>
          <span class="l-code">AR</span>
        </div>
      </div>
    </div>

    <!-- Center Brand Logo (wevtex) -->
    <a href="/menu.php?r=<?= urlencode($rest['slug']) ?>" class="brand-center" aria-label="wevtex">
      <img src="/assets/wevtex-mark.png" alt="Wevtex" class="brand-logo-img">
      <span class="brand-wevtex-logo">wevtex</span>
    </a>

    <!-- Right Actions -->
    <div class="top-actions">
      <button class="icon-btn" onclick="toggleSearch()" title="Rechercher">
        <span class="msi" aria-hidden="true">search</span>
      </button>
      <button class="icon-btn" onclick="openCartModal()" title="Panier">
        <span class="msi" aria-hidden="true">shopping_bag</span>
        <span class="badge-count" id="headerCartCount" style="display:none;">0</span>
      </button>
    </div>
  </div>
</header>

<main class="main-wrapper">
  <!-- Section 1: Nos Spécialités -->
  <div class="section-head">
    <h2 class="section-title" id="sectionTitleSpecialties">Nos Spécialités</h2>
    <div class="pill-popular" id="badgePopular">Populaire</div>
  </div>

  <!-- Horizontal Categories Carousel (Sticky with Navbar) -->
  <div class="specialties-sticky-container">
    <div class="specialties-carousel">
      <div class="spec-item active" onclick="filterCategory('all', this)">
        <div class="spec-ring">
          <div class="spec-inner">
            <img src="/uploads/<?= e($firstPhoto) ?>" alt="Tout">
          </div>
        </div>
        <span class="spec-label" id="specLabelAll">Tout</span>
      </div>

      <?php foreach ($categories as $c): ?>
        <?php $cPhoto = $catPhotos[$c['id']] ?? 'dish_12_v1.jpg'; ?>
        <div class="spec-item" onclick="filterCategory('<?= (int)$c['id'] ?>', this)">
          <div class="spec-ring">
            <div class="spec-inner">
              <img src="/uploads/<?= e($cPhoto) ?>" alt="<?= e($c['name']) ?>">
            </div>
          </div>
          <span class="spec-label" data-cat-id="<?= (int)$c['id'] ?>" data-cat-name="<?= e($c['name']) ?>"><?= e($c['name']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Search Input -->
  <div class="search-box-wrap" id="searchBoxWrap" style="display:none;">
    <span class="msi" aria-hidden="true">search</span>
    <input type="text" class="search-input" id="dishSearchInput" placeholder="Rechercher un plat..." oninput="filterDishesBySearch(this.value)">
  </div>

  <!-- Section 2: Nos Plats -->
  <div class="section-head" style="margin-top: 10px;">
    <h2 class="section-title" id="sectionTitleDishes">Nos Plats</h2>
  </div>

  <!-- 2-Column Dishes Grid -->
  <div class="dishes-grid" id="dishesGrid">
    <?php foreach ($dishes as $d): ?>
      <?php 
        $badgeClass = '';
        $badgeText = '';
        $flag = strtolower((string)$d['flags']);
        if (str_contains($flag, 'best') || str_contains($flag, 'bestseller')) {
            $badgeClass = 'bestseller';
            $badgeText = 'Best Seller';
        } elseif (str_contains($flag, 'nouveau') || str_contains($flag, 'new')) {
            $badgeClass = 'nouveau';
            $badgeText = '🔥 Nouveau';
        } elseif (str_contains($flag, 'populaire')) {
            $badgeClass = 'populaire';
            $badgeText = 'Populaire';
        } elseif ((int)$d['is_special'] === 1) {
            $badgeClass = 'nouveau';
            $badgeText = 'Chef Special';
        }
      ?>
      <!-- Tapping the card opens the Bottom Sheet sliding up to navbar! -->
      <div class="dish-card" data-cat="<?= (int)$d['category_id'] ?>" data-name="<?= strtolower(e($d['name'])) ?>" onclick="openDishBottomSheet(<?= (int)$d['id'] ?>)">
        <div class="dish-card-img-wrap">
          <?php if ($badgeText): ?>
            <div class="card-badge <?= $badgeClass ?>"><?= $badgeText ?></div>
          <?php endif; ?>

          <img src="/uploads/<?= e($d['photo']) ?>" alt="<?= e($d['name']) ?>" loading="lazy">

          <div class="card-dots">
            <span class="card-dot active"></span>
            <span class="card-dot"></span>
            <span class="card-dot"></span>
            <span class="card-dot"></span>
            <span class="card-dot"></span>
          </div>
        </div>

        <div class="dish-card-body">
          <div>
            <h3 class="dish-name" data-dish-name-id="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></h3>
            <p class="dish-short-desc" data-dish-desc-id="<?= (int)$d['id'] ?>"><?= e($d['description']) ?></p>
          </div>
          <div class="dish-card-footer">
            <span class="dish-price"><?= number_format((float)$d['price'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></span>
            <button class="btn-add-plus" onclick="event.stopPropagation(); quickAddToCart(<?= (int)$d['id'] ?>)" title="Ajouter">
              <span class="msi" aria-hidden="true">add</span>
            </button>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<!-- Luxury Brand Footer -->
<footer class="app-luxury-footer">
  <div class="footer-container">
    <div class="footer-brand-section">
      <div class="footer-emblem-wrap">
        <?php $brandLogo = !empty($rest['logo']) ? '/uploads/' . $rest['logo'] : '/assets/wevtex-mark-light.png'; ?>
        <img src="<?= e($brandLogo) ?>" alt="<?= e($rest['name'] ?: 'Wevtex') ?>" class="footer-wevtex-logo">
      </div>
      <h3 class="footer-brand-title"><?= e($rest['name'] ?: 'Maison Sola') ?></h3>
      <p class="footer-tagline" id="footerTagline">Haute Gastronomie & Saveurs Authentiques</p>
      <div class="footer-ornament"><span>✦</span></div>
    </div>

    <div class="footer-info-grid">
      <div class="footer-info-item">
        <div class="footer-info-icon">
          <span class="msi" aria-hidden="true">location_on</span>
        </div>
        <div class="footer-info-text">
          <span class="footer-info-label" id="footerLabelAddress">Adresse</span>
          <span class="footer-info-val"><?= e($rest['address'] ?: 'Casablanca, Maroc') ?></span>
        </div>
      </div>

      <div class="footer-info-item">
        <div class="footer-info-icon">
          <span class="msi" aria-hidden="true">schedule</span>
        </div>
        <div class="footer-info-text">
          <span class="footer-info-label" id="footerLabelHours">Horaires de service</span>
          <span class="footer-info-val" id="footerValHours">Tous les jours : 12h00 – 00h00</span>
        </div>
      </div>

      <div class="footer-info-item">
        <div class="footer-info-icon">
          <span class="msi" aria-hidden="true">call</span>
        </div>
        <div class="footer-info-text">
          <span class="footer-info-label" id="footerLabelContact">Réservations & Contact</span>
          <a href="tel:<?= preg_replace('/\s+/', '', $rest['phone'] ?: '+212600000000') ?>" class="footer-info-val footer-phone-link"><?= e($rest['phone'] ?: '+212 6 00 00 00 00') ?></a>
        </div>
      </div>
    </div>

    <!-- Social Media Links -->
    <div class="footer-social-section">
      <span class="footer-social-title" id="footerLabelFollow">Suivez notre univers</span>
      <div class="footer-social-pills">
        <a href="https://instagram.com" target="_blank" rel="noopener" class="footer-social-btn ig-btn" title="Instagram">
          <svg class="brand-ico" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.2c3.2 0 3.6 0 4.85.07 1.17.05 1.8.25 2.23.42.56.22.96.48 1.38.9.42.42.68.82.9 1.38.17.42.37 1.05.42 2.23.06 1.26.07 1.65.07 4.85s0 3.6-.07 4.85c-.05 1.18-.25 1.8-.42 2.23-.22.56-.48.96-.9 1.38a3.7 3.7 0 0 1-1.38.9c-.42.17-1.05.37-2.23.42-1.26.06-1.65.07-4.85.07s-3.6 0-4.85-.07c-1.18-.05-1.8-.25-2.23-.42a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.17-.42-.37-1.05-.42-2.23C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.85c.05-1.17.25-1.8.42-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.17 1.05-.37 2.23-.42C8.4 2.2 8.8 2.2 12 2.2M12 0C8.74 0 8.33 0 7.05.07 5.78.13 4.9.33 4.14.63a5.9 5.9 0 0 0-2.13 1.38A5.9 5.9 0 0 0 .63 4.14C.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s0 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.31.78.72 1.45 1.38 2.11a5.9 5.9 0 0 0 2.13 1.38c.76.3 1.64.5 2.91.56C8.33 24 8.74 24 12 24s3.67 0 4.95-.07c1.27-.06 2.15-.26 2.91-.56a5.9 5.9 0 0 0 2.13-1.38 5.9 5.9 0 0 0 1.38-2.11c.3-.76.5-1.64.56-2.91.07-1.28.07-1.69.07-4.95s0-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91a5.9 5.9 0 0 0-1.38-2.13A5.9 5.9 0 0 0 19.86.63C19.1.33 18.22.13 16.95.07 15.67 0 15.26 0 12 0zm0 5.84A6.16 6.16 0 1 0 18.16 12 6.16 6.16 0 0 0 12 5.84zm0 10.16A4 4 0 1 1 16 12a4 4 0 0 1-4 4zm6.4-11.84a1.44 1.44 0 1 0 1.44 1.44 1.44 1.44 0 0 0-1.44-1.44z"/></svg>
          <span>Instagram</span>
        </a>

        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $rest['phone'] ?: '212600000000') ?>" target="_blank" rel="noopener" class="footer-social-btn whatsapp-btn" title="WhatsApp">
          <svg class="brand-ico" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-1 1.2-.4.2-.7.1a8.1 8.1 0 0 1-2.4-1.5 9 9 0 0 1-1.6-2c-.2-.3 0-.5.1-.6s.3-.3.4-.5.2-.3.3-.5.1-.4 0-.5l-1-2.3c-.2-.6-.5-.5-.7-.5h-.6a1.1 1.1 0 0 0-.8.4 3.3 3.3 0 0 0-1 2.5 5.8 5.8 0 0 0 1.2 3.1c.2.2 2.1 3.2 5 4.5a16.8 16.8 0 0 0 1.7.6 4 4 0 0 0 1.8.1c.6-.1 1.8-.7 2-1.4a2.5 2.5 0 0 0 .2-1.4c-.1-.1-.3-.2-.6-.3zm-5.5 7.5h0a10 10 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.3-.4a9.9 9.9 0 1 1 8.4 4.6zM20.5 3.5A11.8 11.8 0 0 0 2 17.9L.3 24l6.2-1.6a11.8 11.8 0 0 0 5.6 1.4h0a11.8 11.8 0 0 0 11.9-11.8 11.7 11.7 0 0 0-3.5-8.5z"/></svg>
          <span>WhatsApp</span>
        </a>

        <a href="https://tiktok.com" target="_blank" rel="noopener" class="footer-social-btn tt-btn" title="TikTok">
          <svg class="brand-ico" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.29 0 .58.04.85.12V9.4a6.33 6.33 0 0 0-1-.08A6.34 6.34 0 0 0 3 15.66a6.34 6.34 0 0 0 10.82 4.49 6.3 6.3 0 0 0 1.86-4.49V8.58a8.28 8.28 0 0 0 4.84 1.56V6.69z"/></svg>
          <span>TikTok</span>
        </a>

        <a href="https://facebook.com" target="_blank" rel="noopener" class="footer-social-btn fb-btn" title="Facebook">
          <svg class="brand-ico" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.61 23.09 24 18.1 24 12.07z"/></svg>
          <span>Facebook</span>
        </a>

        <a href="https://maps.google.com" target="_blank" rel="noopener" class="footer-social-btn maps-btn" title="Google Maps">
          <svg class="brand-ico" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a7.46 7.46 0 0 0-7.46 7.46c0 6.52 6.78 11.9 7.07 12.13a.65.65 0 0 0 .78 0c.29-.23 7.07-5.61 7.07-12.13A7.46 7.46 0 0 0 12 2zm0 10.76a3.3 3.3 0 1 1 3.3-3.3 3.3 3.3 0 0 1-3.3 3.3z"/></svg>
          <span>Maps</span>
        </a>
      </div>
    </div>

    <!-- Quality Badges -->
    <div class="footer-badges-row">
      <span class="footer-badge">✦ 100% Fait Maison</span>
      <span class="footer-badge">✦ Ingrédients Frais</span>
      <span class="footer-badge">✦ Viande Certifiée Halal</span>
    </div>

    <div class="footer-divider-line"></div>

    <!-- Bottom Credits -->
    <div class="footer-bottom-row">
      <p class="footer-copy">© <?= date('Y') ?> <?= e($rest['name'] ?: 'Maison Sola') ?>. <span id="footerAllRights">Tous droits réservés.</span></p>
      <div class="footer-powered">
        <span id="footerPoweredTxt">Propulsé avec élégance par</span>
        <a href="https://wevtex.com" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;color:#FFFFFF;vertical-align:middle;margin-left:4px;">
          <img src="/assets/wevtex-mark-light.png" alt="Wevtex" style="height:17px;width:auto;">
          <strong style="color:#FFFFFF;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;letter-spacing:-0.02em;">wevtex</strong>
        </a>
      </div>
    </div>
  </div>
</footer>

<!-- Floating Bottom Bar -->
<div class="floating-bottom-bar" id="floatingBottomBar">
  <div class="order-summary-txt" onclick="openCartModal()" style="cursor:pointer;">
    <span id="floatingOrderItems">Voir la commande (0 item)</span>
    <span class="sub-total" id="floatingOrderTotal">0 <?= e($CURRENCY) ?></span>
  </div>
  <button class="btn-view-cart" onclick="openCartModal()">
    <span class="msi sm" aria-hidden="true">shopping_bag</span>
    <span id="floatingViewCartBtn">Voir le panier</span>
  </button>
</div>

<!-- ========================================================
     DISH DETAIL BOTTOM SHEET (SLIDES UP FROM BOTTOM TO NAVBAR)
     ======================================================== -->
<div class="dish-sheet-overlay" id="dishSheetOverlay" onclick="closeDishBottomSheet(event)">
  <div class="dish-sheet-panel" id="dishSheetPanel" onclick="event.stopPropagation()">
    <!-- Top Drag Handle -->
    <div class="sheet-drag-handle-wrap" onclick="closeDishBottomSheet()">
      <div class="sheet-drag-bar"></div>
    </div>

    <!-- Sheet Navigation Bar -->
    <div class="sheet-top-nav">
      <button class="sheet-icon-btn" onclick="closeDishBottomSheet()" title="Fermer">
        <span class="msi" aria-hidden="true">chevron_left</span>
      </button>

      <button class="sheet-icon-btn" onclick="shareCurrentSheetDish()" title="Partager">
        <span class="msi" aria-hidden="true">ios_share</span>
      </button>
    </div>

    <!-- Scrollable Content of the dish -->
    <div class="sheet-scroll-body">
      <!-- 3-Photo Carousel of THAT SAME DISH -->
      <div class="hero-carousel-container" id="sheetCarouselContainer">
        <div class="side-peek left" onclick="changeSheetDishImage('prev')">
          <img src="" id="sheetLeftPeek">
        </div>
        <button class="peek-nav-btn left-btn" onclick="changeSheetDishImage('prev')">
          <span class="msi" aria-hidden="true">chevron_left</span>
        </button>
        <div class="main-dish-visual">
          <img src="" id="sheetMainPhoto">
        </div>
        <button class="peek-nav-btn right-btn" onclick="changeSheetDishImage('next')">
          <span class="msi" aria-hidden="true">chevron_right</span>
        </button>
        <div class="side-peek right" onclick="changeSheetDishImage('next')">
          <img src="" id="sheetRightPeek">
        </div>
      </div>

      <!-- Dots Indicator -->
      <div class="carousel-dots-row" id="sheetDotsRow">
        <span class="carousel-dot active"></span>
        <span class="carousel-dot inactive"></span>
        <span class="carousel-dot inactive"></span>
      </div>

      <!-- Dish Title & Price -->
      <div class="dish-header-center">
        <h2 class="dish-title-sheet" id="sheetDishTitle">Burger Sola</h2>
        <div class="dish-price-sheet" id="sheetDishPrice">79 DH</div>
        <p class="dish-desc-sheet" id="sheetDishDesc"></p>
      </div>

      <!-- Quantity Adjuster -->
      <div class="qty-selector-wrap">
        <button class="qty-btn" onclick="adjustSheetQty(-1)">-</button>
        <span class="qty-number" id="sheetQtyNumber">1</span>
        <button class="qty-btn" onclick="adjustSheetQty(1)">+</button>
      </div>
    </div>

    <!-- Sticky Bottom Button in Sheet -->
    <div class="sheet-bottom-bar">
      <button class="btn-add-cart-large" onclick="handleSheetAddToCart()">
        <span id="sheetBtnAddText">Ajouter au panier</span>
        <span id="sheetBtnPriceTotal">• 79 DH</span>
      </button>
    </div>
  </div>
</div>

<!-- Dish Customization Bottom Sheet Modal -->
<div class="cust-modal-overlay" id="customizationModalOverlay" onclick="closeCustomizationModal(event)">
  <div class="cust-sheet-panel" onclick="event.stopPropagation()">
    <div class="sheet-drag-handle-wrap" onclick="closeCustomizationModal()">
      <div class="sheet-drag-bar"></div>
    </div>
    
    <div class="cust-header">
      <img src="" id="custDishThumb" class="cust-thumb" alt="">
      <div class="cust-header-info">
        <div class="cust-dish-name" id="custDishName">Nom du plat</div>
        <div class="cust-dish-baseprice" id="custDishBasePrice">0 DH</div>
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
      <button type="button" class="cust-tab-copy-btn" id="btnApplyAll" onclick="applyCustToAll()" title="Copier les options du plat actuel sur tous les autres">
        <span>📋 Idem pour tous</span>
      </button>
    </div>

    <div class="cust-scroll-body" id="custOptionsBody">
      <!-- Dynamically generated groups & options -->
    </div>

    <div class="cust-bottom-bar">
      <div class="cust-qty-row">
        <span style="font-size:13.5px; font-weight:700; color:var(--text-dark);" id="custQtyLabel">Quantité</span>
        <div class="qty-selector-wrap" style="margin:0;">
          <button class="qty-btn" onclick="adjustCustQty(-1)">-</button>
          <span class="qty-number" id="custQtyNumber">1</span>
          <button class="qty-btn" onclick="adjustCustQty(1)">+</button>
        </div>
      </div>
      <button class="btn-add-cart-large" onclick="confirmCustomizationAndAdd()">
        <span id="custBtnText">Confirmer & Ajouter</span>
        <span id="custBtnTotal">• 0 DH</span>
      </button>
    </div>
  </div>
</div>

<!-- Cart / Order Bottom Sheet Modal (Luxury Design matching user screenshot) -->
<div class="modal-overlay" id="cartModalOverlay" onclick="closeCartModal(event)">
  <div class="cart-bottom-sheet" onclick="event.stopPropagation()">
    <!-- Top Nav Header -->
    <div class="cart-sheet-top-bar">
      <button type="button" class="cart-nav-icon-btn" onclick="closeCartModal(event)" title="Retour">
        <span class="msi" aria-hidden="true">chevron_left</span>
      </button>
      <h2 class="cart-sheet-heading" id="cartModalTitle">Mon Panier</h2>
      <button type="button" class="cart-nav-icon-btn" onclick="shareCartOrClose()" title="Partager">
        <span class="msi" aria-hidden="true">ios_share</span>
      </button>
    </div>

    <!-- Scrollable Body -->
    <div class="cart-sheet-scroll-body">
      <!-- Active Cart Content -->
      <div id="cartActiveContent">
        <!-- Dish Items Cards -->
        <div id="cartItemsList">
          <p style="color:var(--text-muted); text-align:center; padding: 24px 0;" id="cartEmptyNotice">Votre panier est actuellement vide.</p>
        </div>

        <!-- Total Row -->
        <div class="cart-total-header-row" id="cartTotalBox" style="display:none;">
          <span class="cart-total-title-txt" id="cartTotalLabel">Total :</span>
          <span class="cart-total-sum-txt" id="cartTotalSum">0 <?= e($CURRENCY) ?></span>
        </div>

        <!-- Vos Informations Section -->
        <div class="cart-user-info-section" id="cartCustomerSection" style="display:none;">
          <h3 class="cart-user-info-title" id="cartInfoHeading">Vos Informations</h3>

          <div class="cart-form-group">
            <label class="cart-form-label" id="cartNameLabel">Nom complet</label>
            <input type="text" class="cart-clean-input" id="cartCustomerName" placeholder="Votre nom" autocomplete="name">
          </div>

          <div class="cart-form-group">
            <label class="cart-form-label" id="cartTableLabel">Numéro de table <span style="color:var(--accent-terracotta);">*</span></label>
            <input type="text" class="cart-clean-input" id="cartTableNumber" placeholder="Ex: Table 4" value="<?= e($tableParam) ?>" autocomplete="off">
          </div>

          <button type="button" class="cart-confirm-cta-btn" id="btnCheckout" onclick="proceedToOrder()">
            <span id="btnCheckoutText">Confirmer la commande</span>
          </button>
        </div>
      </div>

      <!-- Order Success View (shown after confirming order directly to admin) -->
      <div id="cartSuccessView" style="display:none; padding: 24px 8px; text-align: center;">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: #DCFCE7; color: #16A34A; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
          <span class="msi xl" style="font-size:40px; font-variation-settings: 'FILL' 1, 'wght' 700, 'GRAD' 0, 'opsz' 40;" aria-hidden="true">check_circle</span>
        </div>
        <h3 style="font-family: var(--font-serif); font-size: 24px; font-weight: 700; color: var(--text-dark); margin: 0 0 8px;" id="orderSuccessTitle">
          Commande confirmée !
        </h3>
        <p style="font-size: 14.5px; color: #6E6862; line-height: 1.5; margin: 0 0 22px;" id="orderSuccessSub">
          Votre commande a été transmise directement en cuisine. Notre équipe s'occupe de la préparation.
        </p>

        <div style="background: #FFFFFF; border: 1px solid rgba(0,0,0,0.06); border-radius: 18px; padding: 16px; text-align: left; margin-bottom: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.035);">
          <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px;">
            <span style="color: #8C827A;">Statut :</span>
            <span style="font-weight: 700; color: #16A34A; background: #DCFCE7; padding: 2px 8px; border-radius: 99px; font-size: 12px;">En cuisine 🍳</span>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; font-size: 14px;">
            <span style="color: #8C827A;">Table :</span>
            <strong id="successTableNum" style="color: var(--text-dark);">Table</strong>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; font-size: 14px;">
            <span style="color: #8C827A;">Numéro de commande :</span>
            <strong id="successOrderNum" style="color: var(--accent-terracotta);">#---</strong>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px dashed rgba(0,0,0,0.08); font-size: 16px;">
            <span style="font-weight: 700; color: var(--text-dark);">Total :</span>
            <strong id="successTotalSum" style="font-weight: 800; color: var(--text-dark);">0 DH</strong>
          </div>
        </div>

        <button type="button" class="cart-confirm-cta-btn" onclick="resetAndCloseCartModal()" style="margin-bottom: 12px;">
          <span id="orderSuccessBtnBack">Commander autre chose</span>
        </button>

        <button type="button" id="btnWaReceipt" style="width: 100%; background: transparent; border: 1px solid rgba(0,0,0,0.12); color: #19201B; padding: 12px; border-radius: 9999px; font-weight: 600; font-size: 13.5px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="sendWhatsAppReceipt()">
          <span>💬</span> <span id="orderSuccessBtnWa">Recevoir une copie sur WhatsApp</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Notice -->
<div class="toast-notice" id="toastNotice">
  <span class="msi sm" style="color:#22C55E; font-variation-settings:'FILL' 1,'wght' 700,'GRAD' 0,'opsz' 24;" aria-hidden="true">check_circle</span>
  <span id="toastText">Ajouté au panier !</span>
</div>

<script>
const CURRENCY = '<?= e($CURRENCY) ?>';
const ALL_DISHES = <?= json_encode($dishesDataMap) ?>;

// MULTI-LANGUAGE ENGINE (FR / EN / AR)
let currentLang = localStorage.getItem('maison_sola_lang') || 'fr';

const I18N = {
  fr: {
    specialties: "Nos Spécialités",
    popular: "Populaire",
    all: "Tout",
    our_dishes: "Nos Plats",
    search_placeholder: "Rechercher un plat...",
    order_items: "Voir la commande",
    view_cart: "Voir le panier",
    your_cart: "Votre Panier",
    cart_empty: "Votre panier est actuellement vide.",
    cart_total: "Total de la commande",
    whatsapp_order: "Confirmer via WhatsApp",
    add_to_cart: "Ajouter au panier",
    confirm_add: "Confirmer & Ajouter",
    confirm_all: "Confirmer les {n} plats",
    dish_customization: "Options & Personnalisation",
    qty: "Quantité",
    required_choice: "1 choix obligatoire",
    optional_choice: "Optionnel",
    same_for_all: "📋 Idem pour tous",
    custom_options_for: "Options pour : {name} #{i} (sur {n})",
    added_to_cart: "{n}x {name} ajouté au panier !",
    copied_all: "Options copiées sur tous les plats !",
    wa_greeting: "Bonjour *Maison Sola*, je souhaite passer une commande :",
    wa_total: "*Total : {total} {currency}*",
    plat_num: "Plat #{i}",
    inclus: "Inclus",
    footer_tagline: "Haute Gastronomie & Saveurs Authentiques",
    footer_address: "Adresse",
    footer_hours: "Horaires de service",
    footer_hours_val: "Tous les jours : 12h00 – 00h00",
    footer_contact: "Réservations & Contact",
    footer_follow: "Suivez notre univers",
    footer_all_rights: "Tous droits réservés.",
    footer_powered: "Propulsé avec élégance par",
    cart_my_cart: "Mon Panier",
    cart_total_lbl: "Total :",
    cart_info_heading: "Vos Informations",
    cart_name_lbl: "Nom complet",
    cart_name_placeholder: "Votre nom",
    cart_table_lbl: "Numéro de table",
    cart_table_placeholder: "Ex: Table 4",
    cart_confirm_cmd: "Confirmer la commande",
    cart_empty_msg: "Votre panier est actuellement vide.",
    order_success_title: "Commande confirmée !",
    order_success_sub: "Votre commande a été transmise en cuisine. Notre équipe s'occupe de la préparation.",
    order_btn_back: "Commander autre chose",
    order_btn_wa_copy: "Recevoir une copie sur WhatsApp"
  },
  en: {
    specialties: "Our Specialties",
    popular: "Popular",
    all: "All",
    our_dishes: "Our Dishes",
    search_placeholder: "Search a dish...",
    order_items: "View order",
    view_cart: "View cart",
    your_cart: "Your Cart",
    cart_empty: "Your cart is currently empty.",
    cart_total: "Order Total",
    whatsapp_order: "Confirm via WhatsApp",
    add_to_cart: "Add to cart",
    confirm_add: "Confirm & Add",
    confirm_all: "Confirm {n} dishes",
    dish_customization: "Options & Customization",
    qty: "Quantity",
    required_choice: "1 required choice",
    optional_choice: "Optional",
    same_for_all: "📋 Same for all",
    custom_options_for: "Options for: {name} #{i} (of {n})",
    added_to_cart: "{n}x {name} added to cart!",
    copied_all: "Options copied to all dishes!",
    wa_greeting: "Hello *Maison Sola*, I would like to place an order:",
    wa_total: "*Total : {total} {currency}*",
    plat_num: "Dish #{i}",
    inclus: "Included",
    footer_tagline: "Fine Dining & Authentic Flavors",
    footer_address: "Address",
    footer_hours: "Opening Hours",
    footer_hours_val: "Every day: 12:00 PM – 12:00 AM",
    footer_contact: "Reservations & Contact",
    footer_follow: "Follow our story",
    footer_all_rights: "All rights reserved.",
    footer_powered: "Elegantly powered by",
    cart_my_cart: "My Cart",
    cart_total_lbl: "Total :",
    cart_info_heading: "Your Information",
    cart_name_lbl: "Full Name",
    cart_name_placeholder: "Your name",
    cart_table_lbl: "Table Number",
    cart_table_placeholder: "e.g. Table 4",
    cart_confirm_cmd: "Confirm Order",
    cart_empty_msg: "Your cart is currently empty.",
    order_success_title: "Order Confirmed!",
    order_success_sub: "Your order has been sent directly to the kitchen. Our team is preparing your dishes.",
    order_btn_back: "Order something else",
    order_btn_wa_copy: "Receive a copy on WhatsApp"
  },
  ar: {
    specialties: "أطباقنا المميزة",
    popular: "الأكثر طلباً",
    all: "الكل",
    our_dishes: "قائمتنا",
    search_placeholder: "ابحث عن وجبة...",
    order_items: "عرض الطلب",
    view_cart: "سلة الطلبات",
    your_cart: "سلة طلباتك",
    cart_empty: "سلتك فارغة حالياً.",
    cart_total: "المجموع الإجمالي",
    whatsapp_order: "تأكيد الطلب عبر واتساب",
    add_to_cart: "أضف إلى السلة",
    confirm_add: "تأكيد وإضافة",
    confirm_all: "تأكيد الـ {n} وجبات",
    dish_customization: "تخصيص الخيارات",
    qty: "الكمية",
    required_choice: "اختيار إلزامي",
    optional_choice: "اختياري",
    same_for_all: "📋 نفس الخيارات للكل",
    custom_options_for: "خيارات : {name} رقم {i} (من {n})",
    added_to_cart: "تمت إضافة {n}x {name} إلى السلة !",
    copied_all: "تم نسخ الخيارات لجميع الأطباق !",
    wa_greeting: "السلام عليكم *Maison Sola*، أود تقديم طلبية :",
    wa_total: "*المجموع الإجمالي : {total} {currency}*",
    plat_num: "طبق #{i}",
    inclus: "شامل",
    footer_tagline: "فنون الطهي الراقي والأصالة المغربية",
    footer_address: "العنوان",
    footer_hours: "أوقات العمل",
    footer_hours_val: "طيلة أيام الأسبوع : 12:00 زوالاً – 00:00 ليلاً",
    footer_contact: "الحجز والتواصل",
    footer_follow: "تابعونا على شبكات التواصل",
    footer_all_rights: "جميع الحقوق محفوظة.",
    footer_powered: "مشغل بأناقة بواسطة",
    cart_my_cart: "سلتي",
    cart_total_lbl: "المجموع :",
    cart_info_heading: "معلومات الطلب",
    cart_name_lbl: "الاسم الكامل",
    cart_name_placeholder: "اسمك الكريم",
    cart_table_lbl: "رقم الطاولة",
    cart_table_placeholder: "مثال: طاولة 4",
    cart_confirm_cmd: "تأكيد الطلبية",
    cart_empty_msg: "سلتك فارغة حالياً.",
    order_success_title: "تم تأكيد طلبيتك بنجاح !",
    order_success_sub: "تم إرسال طلبيتك مباشرة إلى المطبخ. فريقنا يعمل على تحضيرها الآن.",
    order_btn_back: "طلب وجبات أخرى",
    order_btn_wa_copy: "الحصول على نسخة عبر واتساب"
  }
};

const CAT_TRANSLATIONS = {
  "Burgers": { en: "Burgers", ar: "برغر" },
  "Pizzas": { en: "Pizzas", ar: "بيتزا" },
  "Pâtes": { en: "Pasta", ar: "معكرونة" },
  "Salades": { en: "Salads", ar: "سلطات" },
  "Desserts": { en: "Desserts", ar: "حلويات" },
  "Boissons": { en: "Drinks", ar: "مشروبات" }
};

const DISH_TRANSLATIONS = {
  12: {
    en: { name: "Burger Sola", desc: "Signature beef patty, mature cheddar, secret sauce, caramelized onions." },
    ar: { name: "برغر سولا", desc: "لحم بقري مفروم فاخر، شيدر معتق، صلصة الدار السرية، بصل مكرمل." }
  },
  13: {
    en: { name: "Burger Classic", desc: "Savory beef, crispy lettuce, fresh tomato, pickles, cocktail sauce." },
    ar: { name: "برغر كلاسيك", desc: "لحم بقري شهي، خس مقرمش، طماطم طازجة، مخلل وصلصة كوكتيل." }
  },
  14: {
    en: { name: "Burger Truffe", desc: "Black Angus beef, truffle cream, fontina cheese, sautéed mushrooms." },
    ar: { name: "برغر الكمأة (التروف)", desc: "لحم أنغوس أسود، كريمة الكمأة، جبن فونتينا وفطر سوتيه." }
  },
  15: {
    en: { name: "Pizza Tartufo", desc: "Fior di latte, black truffle cream, button mushrooms, fresh parmesan." },
    ar: { name: "بيتزا تارتوفو", desc: "جبن فيور دي لاتي، كريمة الكمأة السوداء، فطر طازج، بارميزان." }
  },
  16: {
    en: { name: "Pizza Margherita", desc: "San Marzano tomato sauce, fresh mozzarella, aromatic basil, extra virgin olive oil." },
    ar: { name: "بيتزا مارغريتا", desc: "صلصة طماطم سان مارزانو، موزاريلا طازجة، حبق عطري وزيت زيتون بكر." }
  },
  17: {
    en: { name: "Pizza 4 Fromages", desc: "Gorgonzola, mozzarella, creamy taleggio, aged parmesan." },
    ar: { name: "بيتزا 4 أجبان", desc: "موزاريلا، جورجونزولا، تاليجيو كريمي وجبن بارميزان معتق." }
  },
  18: {
    en: { name: "Pâtes Pesto", desc: "Al dente spaghetti, homemade Genovese basil pesto, roasted pine nuts, parmesan." },
    ar: { name: "باستا بيستو", desc: "معكرونة أل دينتي، بيستو الحبق الإيطالي الطازج، صنوبر محمص وبارميزان." }
  },
  19: {
    en: { name: "Spaghetti Carbonara", desc: "Italian guanciale, fresh egg yolks, pecorino romano, cracked black pepper." },
    ar: { name: "سباغيتي كاربونارا", desc: "جوانشيالي إيطالي، صفار بيض طازج، جبن بيكورينو وفلفل أسود مجروش." }
  },
  20: {
    en: { name: "Penne Arrabbiata", desc: "Spicy tomato sauce, garlic, Calabrian red chili peppers, fresh parsley." },
    ar: { name: "بيني أرابياتا الحارة", desc: "صلصة طماطم حارة، ثوم طازج، فلفل أحمر حار وبقدونس." }
  },
  21: {
    en: { name: "Salade César", desc: "Crispy romaine, grilled chicken breast, golden garlic croutons, aged parmesan, Caesar dressing." },
    ar: { name: "سلطة سيزر", desc: "خس روماني مقرمش، صدر دجاج مشوي، خبز محمص بالثوم، بارميزان وصلصة سيزر." }
  },
  22: {
    en: { name: "Salade Burrata", desc: "Creamy whole burrata, heirloom tomatoes, fresh pesto, 12-year balsamic glaze." },
    ar: { name: "سلطة بوراتا", desc: "جبن البوراتا الإيطالي الكريمي، طماطم كرزية، بيستو وصلصة بلسميك معتقة." }
  },
  23: {
    en: { name: "Tiramisu Maison", desc: "Traditional recipe with creamy mascarpone, espresso-dipped ladyfingers, pure cocoa." },
    ar: { name: "تيراميسو الدار", desc: "الوصفة التقليدية مع جبن الماسكاربوني، بسكويت بالقهوة وكاكاو مر فاخر." }
  },
  24: {
    en: { name: "Fondant Chocolat", desc: "Warm Valrhona dark chocolate lava cake with Madagascar vanilla bean ice cream." },
    ar: { name: "فوندان الشوكولاتة", desc: "كيك الشوكولاتة الذائبة الدافئة مع آيس كريم فانيليا مدغشقر الطبيعية." }
  },
  25: {
    en: { name: "Limonade Maison", desc: "Pressed lemon juice, fresh Moroccan mint, crushed ice, pure cane sugar." },
    ar: { name: "ليموناضة طازجة بالنعناع", desc: "عصير ليمون طبيعي معصور، نعناع مغربي طازج، ثلج مجروش وسكر قصب طبيعي." }
  },
  26: {
    en: { name: "Ice Tea Pêche", desc: "Infused black tea with sweet white peach nectar, lemon slice and crushed ice." },
    ar: { name: "شاي مثلج بالخوخ", desc: "شاي أسود مع نكتار الخوخ الطبيعي، شريحة ليمون وثلج مجروش." }
  },
  27: {
    en: { name: "Café Espresso", desc: "100% Arabica artisanal roast, rich golden crema, intense velvety finish." },
    ar: { name: "قهوة إسبريسو", desc: "حبوب أرابيكا 100% تحميص خاص، كريمة ذهبية كثيفة ومذاق غني." }
  }
};

const OPT_TRANSLATIONS = {
  // Group names
  "Cuisson": { en: "Cooking", ar: "درجة الطهي" },
  "Accompagnements": { en: "Sides & Accompaniments", ar: "المقبلات والمرافقات" },
  "Choix de Sauce": { en: "Sauce Choice", ar: "اختيار الصلصة" },
  "Cuisson de la viande": { en: "Meat Doneness", ar: "درجة طهي اللحم" },
  "Suppléments & Extras": { en: "Extras & Add-ons", ar: "إضافات ومقبلات" },
  "Type de Pâte": { en: "Crust Type", ar: "نوع العجينة" },
  "Suppléments Gourmands": { en: "Toppings & Extras", ar: "إضافات مميزة" },
  "Intensité Pimentée": { en: "Spice Level", ar: "درجة الحرارة" },
  "Finition Fromage": { en: "Cheese Finish", ar: "لمسة الجبن" },
  "Vinaigrette & Sauce": { en: "Dressing Choice", ar: "الصلصة والتتبيلة" },
  "Suppléments": { en: "Add-ons", ar: "إضافات" },
  "Type d’extraction": { en: "Extraction Type", ar: "نوع الإسبريسو" },
  "Sucre": { en: "Sugar", ar: "السكر" },
  "Variante de saveur": { en: "Flavor Variant", ar: "النكهة" },
  "Niveau de sucre": { en: "Sugar Level", ar: "مستوى السكر" },

  // Items
  "Moyenne": { en: "Medium", ar: "متوسط" },
  "À point": { en: "Medium well", ar: "نصف مطهو" },
  "🍟 Fries": { en: "🍟 Fries", ar: "🍟 بطاطس مقلية" },
  "🥗 Salad": { en: "🥗 Salad", ar: "🥗 سلطة خضراء" },
  "🥫 Sauce": { en: "🥫 Sauce", ar: "🥫 صلصة" },
  "🧀 Double Cheddar": { en: "🧀 Double Cheddar", ar: "🧀 دبل جبن شيدر" },
  "🥓 Bacon Croustillant": { en: "🥓 Crispy Bacon", ar: "🥓 بيكون مقرمش" },
  "Sauce Maison Sola (Douce)": { en: "House Sauce (Mild)", ar: "صلصة الدار الخاصة (خفيفة)" },
  "Sauce Algérienne (Piquante 🌶️)": { en: "Algerian Sauce (Spicy 🌶️)", ar: "صلصة جزائرية (حارة 🌶️)" },
  "Sauce Barbecue Fumée": { en: "Smoky BBQ Sauce", ar: "صلصة باربيكيو مدخنة" },
  "Sauce Fromagère Onctueuse": { en: "Creamy Cheese Sauce", ar: "صلصة الجبن الكريمية" },
  "À point (Recommandé)": { en: "Medium (Recommended)", ar: "متوسط الطهي (موصى به)" },
  "Bien cuit": { en: "Well Done", ar: "مطهو جيداً" },
  "Saignant": { en: "Rare", ar: "نصف مطهو" },
  "Double Cheddar Fondu": { en: "Double Melted Cheddar", ar: "دوبل جبن شيدر ذائب" },
  "Bacon de Bœuf Croustillant": { en: "Crispy Beef Bacon", ar: "بيكون بقري مقرمش" },
  "Portion Frites Maison": { en: "Homemade French Fries", ar: "بطاطس مقلية مقرمشة" },
  "Sans oignons": { en: "No Onions", ar: "بدون بصل" },
  "Pâte Fine & Croustillante": { en: "Thin & Crispy Crust", ar: "عجينة رقيقة ومقرمشة" },
  "Pâte Moelleuse Napolitaine": { en: "Neapolitan Crust", ar: "عجينة نابولية هشة" },
  "Bordure Fourrée Fromage": { en: "Cheese Stuffed Crust", ar: "حواف محشوة بالجبن" },
  "Extra Mozzarella di Bufala": { en: "Extra Buffalo Mozzarella", ar: "موزاريلا بوفالو إضافية" },
  "Huile Pimentée Maison 🌶️": { en: "House Chili Oil 🌶️", ar: "زيت حار خاص 🌶️" },
  "Champignons Frais": { en: "Fresh Mushrooms", ar: "فطر طازج" },
  "Olives Noires Marinées": { en: "Marinated Black Olives", ar: "زيتون أسود متبل" },
  "Doux (Sans piquant)": { en: "Mild (Not spicy)", ar: "خفيف (غير حار)" },
  "Modéré 🌶️": { en: "Medium 🌶️", ar: "متوسط الحرارة 🌶️" },
  "Épicé Relevé 🌶️🌶️": { en: "Hot & Spicy 🌶️🌶️", ar: "حار جداً 🌶️🌶️" },
  "Parmesan Râpé Frais": { en: "Fresh Grated Parmesan", ar: "بارميزان مبشور طازج" },
  "Sans fromage": { en: "No Cheese", ar: "بدون جبن" },
  "Sauce César Traditionnelle": { en: "Traditional Caesar Dressing", ar: "صلصة سيزر الأصلية" },
  "Vinaigrette Balsamique & Miel": { en: "Balsamic & Honey Dressing", ar: "صلصة بلسميك بالعسل" },
  "Huile d’Olive Extra Vierge & Citron": { en: "Extra Virgin Olive Oil & Lemon", ar: "زيت زيتون بكر وليمون" },
  "Poulet Grillé Émincé": { en: "Sliced Grilled Chicken", ar: "قطع دجاج مشوي" },
  "Croûtons Dorés à l’Ail": { en: "Golden Garlic Croutons", ar: "خبز محمص مذهب بالثوم" },
  "Copeaux de Parmesan": { en: "Parmesan Shavings", ar: "رقائق بارميزان" },
  "Espresso Court (Serré)": { en: "Short Espresso (Ristretto)", ar: "إسبريسو مركز (قصير)" },
  "Espresso Normal": { en: "Regular Espresso", ar: "إسبريسو عادي" },
  "Lungo (Allongé)": { en: "Lungo (Long)", ar: "إسبريسو خفيف (طويل)" },
  "Sans sucre": { en: "No Sugar", ar: "بدون سكر" },
  "1 Sucre": { en: "1 Sugar", ar: "قطعة سكر واحدة" },
  "2 Sucres": { en: "2 Sugars", ar: "قطعتين سكر" },
  "Citron & Menthe Fraîche": { en: "Lemon & Fresh Mint", ar: "ليمون ونعناع طازج" },
  "Citron & Fruits Rouges": { en: "Lemon & Red Berries", ar: "ليمون وتوت بري" },
  "Citron & Gingembre": { en: "Lemon & Ginger", ar: "ليمون وزنجبيل" },
  "Normal": { en: "Normal", ar: "عادي" },
  "Peu sucré": { en: "Low Sugar", ar: "سكر خفيف" },
  "Sans sucre ajouté": { en: "No Added Sugar", ar: "بدون سكر مضاف" }
};

const CATEGORIES_MAP = <?= json_encode($categoriesMap) ?>;

function getDishName(id) {
  const d = ALL_DISHES[id];
  if (!d) return '';
  if (currentLang === 'en' && d.name_en) return d.name_en;
  if (currentLang === 'ar' && d.name_ar) return d.name_ar;
  if (currentLang !== 'fr' && DISH_TRANSLATIONS[id] && DISH_TRANSLATIONS[id][currentLang]) {
    return DISH_TRANSLATIONS[id][currentLang].name;
  }
  return d.name;
}

function getDishDesc(id) {
  const d = ALL_DISHES[id];
  if (!d) return '';
  if (currentLang === 'en' && d.description_en) return d.description_en;
  if (currentLang === 'ar' && d.description_ar) return d.description_ar;
  if (currentLang !== 'fr' && DISH_TRANSLATIONS[id] && DISH_TRANSLATIONS[id][currentLang]) {
    return DISH_TRANSLATIONS[id][currentLang].desc;
  }
  return d.description;
}

function getCategoryName(catId, frName) {
  const cat = CATEGORIES_MAP[catId];
  if (cat) {
    if (currentLang === 'en' && cat.name_en) return cat.name_en;
    if (currentLang === 'ar' && cat.name_ar) return cat.name_ar;
    return cat.name;
  }
  if (currentLang !== 'fr' && CAT_TRANSLATIONS[frName] && CAT_TRANSLATIONS[frName][currentLang]) {
    return CAT_TRANSLATIONS[frName][currentLang];
  }
  return frName || '';
}

function getOptionGroupName(grp) {
  if (typeof grp === 'object' && grp !== null) {
    if (currentLang === 'en' && grp.name_en) return grp.name_en;
    if (currentLang === 'ar' && grp.name_ar) return grp.name_ar;
    if (grp.name) return grp.name;
  }
  const frName = typeof grp === 'string' ? grp : (grp ? grp.name : '');
  if (currentLang !== 'fr' && OPT_TRANSLATIONS[frName] && OPT_TRANSLATIONS[frName][currentLang]) {
    return OPT_TRANSLATIONS[frName][currentLang];
  }
  return frName || '';
}

function getOptionItemName(it) {
  if (typeof it === 'object' && it !== null) {
    if (currentLang === 'en' && it.name_en) return it.name_en;
    if (currentLang === 'ar' && it.name_ar) return it.name_ar;
    if (it.name) return it.name;
  }
  const frName = typeof it === 'string' ? it : (it ? it.name : '');
  if (currentLang !== 'fr' && OPT_TRANSLATIONS[frName] && OPT_TRANSLATIONS[frName][currentLang]) {
    return OPT_TRANSLATIONS[frName][currentLang];
  }
  return frName || '';
}

function toggleLangMenu(e) {
  if (e) e.stopPropagation();
  const wrapper = document.getElementById('langWrapper');
  const menu = document.getElementById('langDropdownMenu');
  wrapper.classList.toggle('open');
  menu.classList.toggle('show');
}

function setLanguage(lang) {
  currentLang = lang;
  localStorage.setItem('maison_sola_lang', lang);
  const wrapper = document.getElementById('langWrapper');
  const menu = document.getElementById('langDropdownMenu');
  if (wrapper) wrapper.classList.remove('open');
  if (menu) menu.classList.remove('show');
  applyLanguage();
}

document.addEventListener('click', (e) => {
  const wrapper = document.getElementById('langWrapper');
  const menu = document.getElementById('langDropdownMenu');
  if (wrapper && !wrapper.contains(e.target)) {
    wrapper.classList.remove('open');
    if (menu) menu.classList.remove('show');
  }
});

function applyLanguage() {
  const lang = currentLang;
  const dict = I18N[lang] || I18N.fr;

  document.documentElement.lang = lang;
  document.documentElement.dir = (lang === 'ar' ? 'rtl' : 'ltr');

  const langLabel = document.getElementById('currentLangLabel');
  if (langLabel) langLabel.innerText = lang.toUpperCase();

  // Active state in dropdown
  document.querySelectorAll('.lang-opt-row').forEach(row => {
    const code = row.querySelector('.l-code');
    if (code && code.innerText.toLowerCase() === lang) {
      row.classList.add('active');
    } else {
      row.classList.remove('active');
    }
  });

  // Static section titles & pills
  const titleSpec = document.getElementById('sectionTitleSpecialties');
  if (titleSpec) titleSpec.innerText = dict.specialties;

  const badgePop = document.getElementById('badgePopular');
  if (badgePop) badgePop.innerText = dict.popular;

  const labelAll = document.getElementById('specLabelAll');
  if (labelAll) labelAll.innerText = dict.all;

  const titleDishes = document.getElementById('sectionTitleDishes');
  if (titleDishes) titleDishes.innerText = dict.our_dishes;

  const searchInp = document.getElementById('dishSearchInput');
  if (searchInp) searchInp.placeholder = dict.search_placeholder;

  // Category labels
  document.querySelectorAll('[data-cat-name]').forEach(el => {
    const fr = el.getAttribute('data-cat-name');
    const cid = el.getAttribute('data-cat-id') || '0';
    el.innerText = getCategoryName(cid, fr);
  });

  // Dishes in grid
  document.querySelectorAll('[data-dish-name-id]').forEach(el => {
    const id = Number(el.getAttribute('data-dish-name-id'));
    el.innerText = getDishName(id);
  });
  document.querySelectorAll('[data-dish-desc-id]').forEach(el => {
    const id = Number(el.getAttribute('data-dish-desc-id'));
    el.innerText = getDishDesc(id);
  });

  // Floating bottom bar & cart button
  const cartBtnText = document.getElementById('floatingViewCartBtn');
  if (cartBtnText) cartBtnText.innerText = dict.view_cart;

  const sheetAddText = document.getElementById('sheetBtnAddText');
  if (sheetAddText) sheetAddText.innerText = dict.add_to_cart;

  const cartModalTitle = document.getElementById('cartModalTitle');
  if (cartModalTitle) cartModalTitle.innerText = dict.cart_my_cart || dict.your_cart || 'Mon Panier';

  const cartEmptyNotice = document.getElementById('cartEmptyNotice');
  if (cartEmptyNotice) cartEmptyNotice.innerText = dict.cart_empty_msg || dict.cart_empty || 'Votre panier est actuellement vide.';

  const cartTotalLabel = document.getElementById('cartTotalLabel');
  if (cartTotalLabel) cartTotalLabel.innerText = dict.cart_total_lbl || dict.cart_total || 'Total :';

  const cartInfoHeading = document.getElementById('cartInfoHeading');
  if (cartInfoHeading) cartInfoHeading.innerText = dict.cart_info_heading || 'Vos Informations';

  const cartNameLabel = document.getElementById('cartNameLabel');
  if (cartNameLabel) cartNameLabel.innerText = dict.cart_name_lbl || 'Nom complet';

  const cartCustomerName = document.getElementById('cartCustomerName');
  if (cartCustomerName) cartCustomerName.placeholder = dict.cart_name_placeholder || 'Votre nom';

  const cartTableLabel = document.getElementById('cartTableLabel');
  if (cartTableLabel) cartTableLabel.innerHTML = `${dict.cart_table_lbl || 'Numéro de table'} <span style="color:var(--accent-terracotta);">*</span>`;

  const cartTableNumber = document.getElementById('cartTableNumber');
  if (cartTableNumber) cartTableNumber.placeholder = dict.cart_table_placeholder || 'Ex: Table 4';

  const btnCheckoutText = document.getElementById('btnCheckoutText');
  if (btnCheckoutText) btnCheckoutText.innerText = dict.cart_confirm_cmd || dict.whatsapp_order || 'Confirmer la commande';

  const orderSuccessTitle = document.getElementById('orderSuccessTitle');
  if (orderSuccessTitle) orderSuccessTitle.innerText = dict.order_success_title || 'Commande confirmée !';

  const orderSuccessSub = document.getElementById('orderSuccessSub');
  if (orderSuccessSub) orderSuccessSub.innerText = dict.order_success_sub || 'Votre commande a été transmise directement en cuisine.';

  const orderSuccessBtnBack = document.getElementById('orderSuccessBtnBack');
  if (orderSuccessBtnBack) orderSuccessBtnBack.innerText = dict.order_btn_back || 'Commander autre chose';

  const orderSuccessBtnWa = document.getElementById('orderSuccessBtnWa');
  if (orderSuccessBtnWa) orderSuccessBtnWa.innerText = dict.order_btn_wa_copy || 'Recevoir une copie sur WhatsApp';

  const custQtyLabel = document.getElementById('custQtyLabel');
  if (custQtyLabel) custQtyLabel.innerText = dict.qty;

  const btnApplyAll = document.getElementById('btnApplyAll');
  if (btnApplyAll) btnApplyAll.innerHTML = `<span>${dict.same_for_all}</span>`;

  // Luxury Footer texts
  const ftTagline = document.getElementById('footerTagline');
  if (ftTagline) ftTagline.innerText = dict.footer_tagline;

  const ftAddr = document.getElementById('footerLabelAddress');
  if (ftAddr) ftAddr.innerText = dict.footer_address;

  const ftHours = document.getElementById('footerLabelHours');
  if (ftHours) ftHours.innerText = dict.footer_hours;

  const ftValHours = document.getElementById('footerValHours');
  if (ftValHours) ftValHours.innerText = dict.footer_hours_val;

  const ftContact = document.getElementById('footerLabelContact');
  if (ftContact) ftContact.innerText = dict.footer_contact;

  const ftFollow = document.getElementById('footerLabelFollow');
  if (ftFollow) ftFollow.innerText = dict.footer_follow;

  const ftRights = document.getElementById('footerAllRights');
  if (ftRights) ftRights.innerText = dict.footer_all_rights;

  const ftPow = document.getElementById('footerPoweredTxt');
  if (ftPow) ftPow.innerText = dict.footer_powered;

  // If active sheet is open, update its texts
  if (activeDish) {
    document.getElementById('sheetDishTitle').innerText = getDishName(activeDish.id);
    document.getElementById('sheetDishDesc').innerText = getDishDesc(activeDish.id);
  }

  // If customization modal is open, re-render
  if (custDish) {
    document.getElementById('custDishName').innerText = getDishName(custDish.id);
    updateTabsUI();
    renderCustomizationOptions();
    updateCustPrice();
  }

  updateCartUI();
}

let cart = JSON.parse(localStorage.getItem('maison_sola_cart') || '{}');

// Active Sheet State
let activeDish = null;
let currentSheetSlideIdx = 0;
let currentSheetQty = 1;

function openDishBottomSheet(dishId) {
  const d = ALL_DISHES[dishId];
  if (!d) return;

  activeDish = d;
  currentSheetSlideIdx = 0;
  currentSheetQty = 1;

  document.getElementById('sheetDishTitle').innerText = getDishName(d.id);
  document.getElementById('sheetDishPrice').innerText = `${d.price} ${CURRENCY}`;
  document.getElementById('sheetDishDesc').innerText = getDishDesc(d.id);
  document.getElementById('sheetQtyNumber').innerText = '1';
  document.getElementById('sheetBtnPriceTotal').innerText = `• ${d.price} ${CURRENCY}`;

  updateSheetCarousel();

  // Show bottom sheet overlay
  const overlay = document.getElementById('dishSheetOverlay');
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDishBottomSheet(e) {
  const overlay = document.getElementById('dishSheetOverlay');
  overlay.classList.remove('open');
  document.body.style.overflow = '';
}

function updateSheetCarousel() {
  if (!activeDish || !activeDish.photos || activeDish.photos.length === 0) return;
  const photos = activeDish.photos;
  const len = photos.length;

  const prevIdx = (currentSheetSlideIdx - 1 + len) % len;
  const nextIdx = (currentSheetSlideIdx + 1) % len;

  const mainPhoto = document.getElementById('sheetMainPhoto');
  mainPhoto.style.opacity = '0';
  mainPhoto.style.transform = 'scale(0.94)';

  setTimeout(() => {
    mainPhoto.src = '/uploads/' + photos[currentSheetSlideIdx];
    mainPhoto.style.opacity = '1';
    mainPhoto.style.transform = 'scale(1)';
  }, 100);

  document.getElementById('sheetLeftPeek').src = '/uploads/' + photos[prevIdx];
  document.getElementById('sheetRightPeek').src = '/uploads/' + photos[nextIdx];

  // Dots
  const dotsContainer = document.getElementById('sheetDotsRow');
  dotsContainer.innerHTML = photos.map((_, idx) => `
    <span class="carousel-dot ${idx === currentSheetSlideIdx ? 'active' : 'inactive'}" onclick="changeSheetDishImage(${idx})"></span>
  `).join('');
}

function changeSheetDishImage(dir) {
  if (!activeDish || !activeDish.photos) return;
  const len = activeDish.photos.length;
  if (len <= 1) return;

  if (dir === 'next') {
    currentSheetSlideIdx = (currentSheetSlideIdx + 1) % len;
  } else if (dir === 'prev') {
    currentSheetSlideIdx = (currentSheetSlideIdx - 1 + len) % len;
  } else if (typeof dir === 'number') {
    currentSheetSlideIdx = dir;
  }

  updateSheetCarousel();
}

function adjustSheetQty(delta) {
  if (!activeDish) return;
  currentSheetQty = Math.max(1, currentSheetQty + delta);
  document.getElementById('sheetQtyNumber').innerText = currentSheetQty;
  const total = (currentSheetQty * activeDish.price).toLocaleString('fr-FR');
  document.getElementById('sheetBtnPriceTotal').innerText = `• ${total} ${CURRENCY}`;
}



// Customization Modal State with Multi-Dish support
let custDish = null;
let custQty = 1;
let currentCustItemIdx = 0;
let custItems = []; // Array of { groupIdx: [selectedItemIndices] } for each dish 1..custQty

function createDefaultItemOptions() {
  const sel = {};
  if (custDish && custDish.options) {
    custDish.options.forEach((grp, gIdx) => {
      if (grp.type === 'single') {
        sel[gIdx] = [0];
      } else {
        sel[gIdx] = [];
      }
    });
  }
  return sel;
}

function openCustomizationModal(dish, qty = 1) {
  custDish = dish;
  custQty = Math.max(1, qty);
  currentCustItemIdx = 0;
  custItems = [];

  for (let i = 0; i < custQty; i++) {
    custItems.push(createDefaultItemOptions());
  }

  const thumbImg = dish.photo || (dish.photos ? dish.photos[0] : '');
  document.getElementById('custDishThumb').src = '/uploads/' + thumbImg;
  document.getElementById('custDishName').innerText = dish.name;
  document.getElementById('custDishBasePrice').innerText = `${dish.price} ${CURRENCY}`;
  document.getElementById('custQtyNumber').innerText = custQty;

  updateTabsUI();
  renderCustomizationOptions();
  updateCustPrice();

  document.getElementById('customizationModalOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeCustomizationModal(e) {
  document.getElementById('customizationModalOverlay').classList.remove('open');
  if (!document.getElementById('dishSheetOverlay').classList.contains('open')) {
    document.body.style.overflow = '';
  }
}

function updateTabsUI() {
  const bars = [
    { bar: document.getElementById('sheetCustItemTabsBar'), list: document.getElementById('sheetCustTabsList') },
    { bar: document.getElementById('custItemTabsBar'), list: document.getElementById('custTabsList') }
  ];

  const dict = (typeof I18N !== 'undefined' && I18N[currentLang]) ? I18N[currentLang] : {};
  const platPrefix = dict.plat_num ? dict.plat_num.replace('{i}', '') : 'Plat #';

  bars.forEach(({ bar, list }) => {
    if (!bar || !list) return;
    if (custQty > 1) {
      bar.style.display = 'flex';
      let tabsHtml = '';
      for (let i = 0; i < custQty; i++) {
        const isActive = (i === currentCustItemIdx);
        tabsHtml += `
          <button type="button" class="cust-tab-pill ${isActive ? 'active' : ''}" onclick="switchCustItem(${i})">
            ${platPrefix}${i + 1}
          </button>
        `;
      }
      list.innerHTML = tabsHtml;
    } else {
      bar.style.display = 'none';
    }
  });
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
  const dict = (typeof I18N !== 'undefined' && I18N[currentLang]) ? I18N[currentLang] : {};
  showToast(dict.copied_all || 'Options copiées sur tous les plats !');
  if (navigator.vibrate) navigator.vibrate(40);
  updateCustPrice();
}

function adjustCustQty(delta) {
  const newQty = Math.max(1, custQty + delta);
  if (newQty === custQty) return;

  if (newQty > custQty) {
    const prevItem = custItems[custItems.length - 1];
    custItems.push(prevItem ? JSON.parse(JSON.stringify(prevItem)) : createDefaultItemOptions());
    currentCustItemIdx = newQty - 1; // switch to newly added item
  } else {
    custItems.pop();
    if (currentCustItemIdx >= newQty) {
      currentCustItemIdx = newQty - 1;
    }
  }

  custQty = newQty;
  currentSheetQty = newQty;
  const custQtyEl = document.getElementById('custQtyNumber');
  if (custQtyEl) custQtyEl.innerText = custQty;
  const sheetQtyEl = document.getElementById('sheetQtyNumber');
  if (sheetQtyEl) sheetQtyEl.innerText = custQty;

  updateTabsUI();
  renderCustomizationOptions();
  updateCustPrice();
}

function toggleCustOption(gIdx, iIdx) {
  if (!custDish || !custDish.options) return;
  const grp = custDish.options[gIdx];
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
  if (navigator.vibrate) navigator.vibrate(20);
}

function renderCustomizationOptions() {
  if (!custDish || !custDish.options) return;
  const modalBody = document.getElementById('custOptionsBody');

  const dict = (typeof I18N !== 'undefined' && I18N[currentLang]) ? I18N[currentLang] : {
    required_choice: "1 choix obligatoire",
    optional_choice: "Optionnel"
  };

  const currentSel = custItems[currentCustItemIdx] || {};
  let html = '';

  custDish.options.forEach((grp, gIdx) => {
    const isSingle = grp.type === 'single';
    const isRequired = grp.required;
    const grpName = (typeof getOptionGroupName === 'function') ? getOptionGroupName(grp) : (grp.name || '');
    const badgeText = isRequired ? (dict.required_choice || '1 choix obligatoire') : (dict.optional_choice || 'Optionnel');

    html += `
      <div class="cust-group" id="custGroup_${gIdx}">
        <div class="cust-group-head">
          <span class="cust-group-title">${grpName}</span>
          <span class="${isRequired ? 'cust-badge-required' : 'cust-badge-optional'}">
            ${badgeText}
          </span>
        </div>
        <div class="cust-options-list">
    `;

    grp.items.forEach((item, iIdx) => {
      const isSel = currentSel[gIdx] && currentSel[gIdx].includes(iIdx);
      const itemName = (typeof getOptionItemName === 'function') ? getOptionItemName(item) : (item.name || '');
      const priceText = item.price > 0 ? `+${item.price} ${CURRENCY}` : 'Inclus';
      const isFree = item.price === 0;

      html += `
        <div class="cust-opt-card ${isSel ? 'selected' : ''}" onclick="toggleCustOption(${gIdx}, ${iIdx})">
          <div class="cust-opt-left">
            <span class="cust-indicator ${isSingle ? 'radio' : 'checkbox'}"></span>
            <span class="cust-opt-label">${itemName}</span>
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

  if (modalBody) modalBody.innerHTML = html;
}

function calculateCustTotalPrice() {
  if (!custDish) return 0;
  let total = 0;
  for (let k = 0; k < custQty; k++) {
    let itemPrice = custDish.price;
    const itemSel = custItems[k] || {};
    custDish.options.forEach((grp, gIdx) => {
      const selIndices = itemSel[gIdx] || [];
      selIndices.forEach(idx => {
        const item = grp.items[idx];
        if (item && item.price) {
          itemPrice += Number(item.price);
        }
      });
    });
    total += itemPrice;
  }
  return total;
}

function updateCustPrice() {
  const total = calculateCustTotalPrice();
  const dict = (typeof I18N !== 'undefined' && I18N[currentLang]) ? I18N[currentLang] : {};
  const label = custQty > 1 ? (dict.confirm_all ? dict.confirm_all.replace('{n}', custQty) : `Confirmer les ${custQty} plats`) : (dict.confirm_add || 'Confirmer & Ajouter');
  
  const custBtnText = document.getElementById('custBtnText');
  if (custBtnText) custBtnText.innerText = label;
  const custBtnTotal = document.getElementById('custBtnTotal');
  if (custBtnTotal) custBtnTotal.innerText = `• ${total.toLocaleString('fr-FR')} ${CURRENCY}`;

  const sheetBtnAddText = document.getElementById('sheetBtnAddText');
  if (sheetBtnAddText) sheetBtnAddText.innerText = (dict.add_to_cart || 'Ajouter au panier');
  const sheetBtnPriceTotal = document.getElementById('sheetBtnPriceTotal');
  if (sheetBtnPriceTotal) sheetBtnPriceTotal.innerText = `• ${total.toLocaleString('fr-FR')} ${CURRENCY}`;
}

function confirmCustomizationAndAdd() {
  if (!custDish) return;

  // Validation across ALL items
  for (let k = 0; k < custQty; k++) {
    const itemSel = custItems[k] || {};
    for (let gIdx = 0; gIdx < custDish.options.length; gIdx++) {
      const grp = custDish.options[gIdx];
      if (grp.required) {
        const sel = itemSel[gIdx] || [];
        if (sel.length === 0) {
          switchCustItem(k);
          const grpEl = document.getElementById(`custGroup_${gIdx}`);
          if (grpEl) grpEl.scrollIntoView({ behavior: 'smooth' });
          showToast(`Plat #${k + 1} : Veuillez choisir ${grp.name}`);
          return;
        }
      }
    }
  }

  // Add each configured item to the cart!
  for (let k = 0; k < custQty; k++) {
    const itemSel = custItems[k] || {};
    let itemUnitPrice = custDish.price;
    const choiceNames = [];
    const keyParts = [custDish.id];

    custDish.options.forEach((grp, gIdx) => {
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

    const cartKey = keyParts.join('_');
    const customSummary = choiceNames.join(', ');

    if (!cart[cartKey]) {
      cart[cartKey] = {
        cartKey: cartKey,
        id: custDish.id,
        name: custDish.name,
        price: itemUnitPrice,
        photo: custDish.photo || (custDish.photos ? custDish.photos[0] : ''),
        qty: 1,
        customSummary: customSummary
      };
    } else {
      cart[cartKey].qty += 1;
    }
  }

  updateCartUI();
  closeCustomizationModal();
  closeDishBottomSheet();

  showToast(`${custQty}x ${custDish.name} personnalisé${custQty > 1 ? 's' : ''} ajouté${custQty > 1 ? 's' : ''} !`);
  if (navigator.vibrate) navigator.vibrate(50);
}

function handleSheetAddToCart() {
  if (!activeDish) return;
  // If dish has options, open customization modal!
  if (activeDish.options && activeDish.options.length > 0) {
    openCustomizationModal(activeDish, currentSheetQty);
    return;
  }

  const cartKey = String(activeDish.id);
  if (!cart[cartKey]) {
    cart[cartKey] = {
      cartKey: cartKey,
      id: activeDish.id,
      name: activeDish.name,
      price: activeDish.price,
      photo: activeDish.photos[0],
      qty: currentSheetQty,
      customSummary: ''
    };
  } else {
    cart[cartKey].qty += currentSheetQty;
  }

  updateCartUI();
  closeDishBottomSheet();

  showToast(`${currentSheetQty}x ${getDishName(activeDish.id)} ajouté !`);
  if (navigator.vibrate) navigator.vibrate(40);
}

function quickAddToCart(id) {
  const d = ALL_DISHES[id];
  if (!d) return;

  // If dish has options, open customization modal so client can choose!
  if (d.options && d.options.length > 0) {
    openCustomizationModal(d, 1);
    return;
  }

  const cartKey = String(id);
  if (!cart[cartKey]) {
    cart[cartKey] = {
      cartKey: cartKey,
      id: id,
      name: d.name,
      price: d.price,
      photo: d.photo,
      qty: 1,
      customSummary: ''
    };
  } else {
    cart[cartKey].qty++;
  }
  updateCartUI();
  showToast(`1x ${d.name} ajouté !`);
  if (navigator.vibrate) navigator.vibrate(30);
}

function showToast(msg) {
  const toast = document.getElementById('toastNotice');
  document.getElementById('toastText').innerText = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 1800);
}

function updateCartUI() {
  const items = Object.values(cart);
  const totalCount = items.reduce((sum, it) => sum + it.qty, 0);
  const totalSum = items.reduce((sum, it) => sum + (it.qty * it.price), 0);

  const headerBadge = document.getElementById('headerCartCount');
  if (headerBadge) {
    if (totalCount > 0) {
      headerBadge.innerText = totalCount;
      headerBadge.style.display = 'flex';
    } else {
      headerBadge.style.display = 'none';
    }
  }

  const floatingBar = document.getElementById('floatingBottomBar');
  const floatingItems = document.getElementById('floatingOrderItems');
  const floatingTotal = document.getElementById('floatingOrderTotal');
  
  if (floatingBar) {
    if (totalCount > 0) {
      floatingBar.classList.remove('hidden');
      if (floatingItems) floatingItems.innerText = `Voir la commande (${totalCount} item${totalCount > 1 ? 's' : ''})`;
      if (floatingTotal) floatingTotal.innerText = `${totalSum.toLocaleString('fr-FR')} ${CURRENCY}`;
    } else {
      floatingBar.classList.add('hidden');
    }
  }

  const listEl = document.getElementById('cartItemsList');
  const totalBox = document.getElementById('cartTotalBox');
  const customerSection = document.getElementById('cartCustomerSection');
  const totalSumEl = document.getElementById('cartTotalSum');
  const dict = (typeof I18N !== 'undefined' && I18N[currentLang]) ? I18N[currentLang] : (typeof I18N !== 'undefined' ? I18N.fr : {});

  if (totalCount === 0) {
    if (listEl) {
      listEl.innerHTML = `<p style="color:var(--text-muted); text-align:center; padding: 36px 0; font-size:15px;" id="cartEmptyNotice">${dict.cart_empty_msg || dict.cart_empty || 'Votre panier est actuellement vide.'}</p>`;
    }
    if (totalBox) totalBox.style.display = 'none';
    if (customerSection) customerSection.style.display = 'none';
  } else {
    if (listEl) {
      listEl.innerHTML = items.map(it => {
        const itemKey = it.cartKey || String(it.id);
        const itemTotalPrice = (it.price * it.qty).toLocaleString('fr-FR');
        let photoSrc = it.photo ? (it.photo.startsWith('http') || it.photo.startsWith('/') ? it.photo : `/uploads/${it.photo}`) : 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&auto=format&fit=crop&q=80';
        const displayName = (typeof getDishName === 'function') ? getDishName(it.id) : (it.name || '');
        const subDetails = it.customSummary ? it.customSummary : `x${it.qty}`;

        return `
          <div class="cart-item-card">
            <img class="cart-item-thumb" src="${photoSrc}" alt="${displayName}" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&auto=format&fit=crop&q=80'">
            <div class="cart-item-details">
              <div class="cart-item-title">${displayName}</div>
              <div class="cart-item-sub">${subDetails}</div>
              <div class="cart-item-price-val">${itemTotalPrice} ${CURRENCY}</div>
            </div>
            <div class="cart-item-controls">
              <span class="cart-item-qty-badge">x${it.qty}</span>
              <div class="cart-item-stepper">
                <button type="button" class="cart-btn-step minus" onclick="changeQty('${itemKey}', -1)" aria-label="Diminuer">−</button>
                <span class="cart-step-quantity">${it.qty}</span>
                <button type="button" class="cart-btn-step plus" onclick="changeQty('${itemKey}', 1)" aria-label="Augmenter">+</button>
              </div>
            </div>
          </div>
        `;
      }).join('');
    }

    if (totalSumEl) totalSumEl.innerText = `${totalSum.toLocaleString('fr-FR')} ${CURRENCY}`;
    if (totalBox) totalBox.style.display = 'flex';
    if (customerSection) customerSection.style.display = 'block';
  }

  localStorage.setItem('maison_sola_cart', JSON.stringify(cart));
}

function changeQty(cartKey, delta) {
  if (!cart[cartKey]) return;
  cart[cartKey].qty += delta;
  if (cart[cartKey].qty <= 0) delete cart[cartKey];
  updateCartUI();
}

function openCartModal() {
  document.getElementById('cartModalOverlay').classList.add('open');
}
function closeCartModal(e) {
  document.getElementById('cartModalOverlay').classList.remove('open');
}

function filterCategory(catId, el) {
  document.querySelectorAll('.spec-item').forEach(x => x.classList.remove('active'));
  if (el) el.classList.add('active');

  const cards = document.querySelectorAll('.dish-card');
  cards.forEach(card => {
    if (catId === 'all' || card.dataset.cat === String(catId)) {
      card.style.display = 'flex';
    } else {
      card.style.display = 'none';
    }
  });
}

function toggleSearch() {
  const box = document.getElementById('searchBoxWrap');
  if (box.style.display === 'none') {
    box.style.display = 'block';
    document.getElementById('dishSearchInput').focus();
  } else {
    box.style.display = 'none';
    filterDishesBySearch('');
  }
}

function filterDishesBySearch(q) {
  const term = q.trim().toLowerCase();
  const cards = document.querySelectorAll('.dish-card');
  cards.forEach(card => {
    if (!term || card.dataset.name.includes(term)) {
      card.style.display = 'flex';
    } else {
      card.style.display = 'none';
    }
  });
}

let lastConfirmedOrder = null;

async function proceedToOrder() {
  const items = Object.values(cart);
  if (items.length === 0) {
    showToast('Votre panier est vide.');
    return;
  }

  const tableInput = document.getElementById('cartTableNumber');
  const nameInput = document.getElementById('cartCustomerName');
  const btnCheckout = document.getElementById('btnCheckout');
  const btnText = document.getElementById('btnCheckoutText');

  const tableVal = tableInput ? tableInput.value.trim() : '';
  const nameVal = nameInput ? nameInput.value.trim() : '';

  if (!tableVal) {
    if (tableInput) {
      tableInput.focus();
      tableInput.style.borderColor = '#B8532F';
      tableInput.style.boxShadow = '0 0 0 3px rgba(184,83,47,0.2)';
      setTimeout(() => { 
        tableInput.style.borderColor = ''; 
        tableInput.style.boxShadow = '';
      }, 2500);
    }
    showToast(currentLang === 'ar' ? 'المرجو إدخال رقم الطاولة !' : 'Veuillez renseigner votre numéro de table !');
    return;
  }

  const totalSum = items.reduce((sum, it) => sum + (it.qty * it.price), 0);

  // Disable button while transmitting
  if (btnCheckout) {
    btnCheckout.disabled = true;
    btnCheckout.style.opacity = '0.7';
    if (btnText) btnText.innerText = (currentLang === 'ar' ? 'جاري الإرسال للمطبخ...' : 'Transmission en cuisine...');
  }

  try {
    const payload = {
      restaurant_slug: <?= json_encode($rest['slug'] ?? 'maison-sola') ?>,
      table_number: tableVal,
      customer_name: nameVal,
      items: items.map(it => ({
        id: it.id,
        name: (typeof getDishName === 'function') ? getDishName(it.id) : (it.name || ''),
        price: it.price,
        qty: it.qty,
        customSummary: it.customSummary || ''
      })),
      total_amount: totalSum
    };

    const res = await fetch('/order_submit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await res.json();

    if (data.success) {
      lastConfirmedOrder = {
        orderId: data.order_id,
        table: tableVal,
        name: nameVal,
        items: items,
        total: totalSum
      };

      // Reset cart
      cart = {};
      localStorage.removeItem('maison_sola_cart');
      updateCartUI();

      // Show success screen inside sheet
      document.getElementById('cartActiveContent').style.display = 'none';
      const successView = document.getElementById('cartSuccessView');
      document.getElementById('successTableNum').innerText = tableVal;
      document.getElementById('successOrderNum').innerText = `#${data.order_id}`;
      document.getElementById('successTotalSum').innerText = `${totalSum.toLocaleString('fr-FR')} ${CURRENCY}`;
      successView.style.display = 'block';

      if (navigator.vibrate) navigator.vibrate([40, 60, 40]);
    } else {
      alert(data.error || 'Erreur lors de la transmission.');
    }
  } catch (err) {
    console.error(err);
    alert('Erreur réseau. Veuillez réessayer.');
  } finally {
    if (btnCheckout) {
      btnCheckout.disabled = false;
      btnCheckout.style.opacity = '1';
      const dict = (typeof I18N !== 'undefined' && I18N[currentLang]) ? I18N[currentLang] : (typeof I18N !== 'undefined' ? I18N.fr : {});
      if (btnText) btnText.innerText = dict.cart_confirm_cmd || 'Confirmer la commande';
    }
  }
}

function resetAndCloseCartModal() {
  const successView = document.getElementById('cartSuccessView');
  const activeContent = document.getElementById('cartActiveContent');
  if (successView) successView.style.display = 'none';
  if (activeContent) activeContent.style.display = 'block';
  closeCartModal();
}

function sendWhatsAppReceipt() {
  if (!lastConfirmedOrder) return;
  const { orderId, table, name, items, total } = lastConfirmedOrder;
  const isAr = currentLang === 'ar';
  const isEn = currentLang === 'en';

  let greeting = isAr
    ? `السلام عليكم *Maison Sola*، هذه نسخة من طلبيتي :`
    : (isEn ? `Hello *Maison Sola*, receipt for my order:` : `Bonjour *Maison Sola*, voici une copie de ma commande :`);

  let msg = `${greeting}\n\n`;
  msg += `🔖 *${isAr ? 'رقم الطلب' : 'Commande'}* : #${orderId}\n`;
  msg += `📍 *${isAr ? 'رقم الطاولة' : 'Table'}* : ${table}\n`;
  if (name) msg += `👤 *${isAr ? 'الزبون' : 'Client'}* : ${name}\n`;
  msg += `\n📋 *${isAr ? 'الطلبات' : 'Détails'}* :\n`;

  items.forEach(it => {
    const sub = it.price * it.qty;
    const displayName = (typeof getDishName === 'function') ? getDishName(it.id) : (it.name || '');
    const optStr = it.customSummary ? `\n   ↳ _${it.customSummary}_` : '';
    msg += `• *${it.qty}x* ${displayName} : *${sub.toLocaleString('fr-FR')} ${CURRENCY}*${optStr}\n`;
  });

  msg += `\n💰 *Total : ${total.toLocaleString('fr-FR')} ${CURRENCY}*`;

  const phone = '<?= preg_replace('/[^0-9]/', '', $rest['phone'] ?: '212600000000') ?>';
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
}

function shareCartOrClose() {
  const url = window.location.href;
  if (navigator.share) {
    navigator.share({
      title: 'Mon Panier · Maison Sola',
      text: 'Découvrez ma commande chez Maison Sola',
      url: url
    }).catch(() => {});
  } else {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(() => {
        showToast(currentLang === 'ar' ? 'تم نسخ رابط السلة !' : 'Lien du panier copié !');
      }).catch(() => {
        closeCartModal();
      });
    } else {
      closeCartModal();
    }
  }
}

function shareCurrentSheetDish() {
  if (!activeDish) return;
  const url = `${window.location.origin}/dish.php?id=${activeDish.id}`;
  if (navigator.share) {
    navigator.share({ title: `${activeDish.name} · Maison Sola`, url }).catch(() => {});
  } else {
    navigator.clipboard.writeText(url);
    showToast('Lien du plat copié !');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  updateCartUI();
});
</script>

</body>
</html>

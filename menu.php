<?php
require __DIR__ . '/shared/config.php';
require __DIR__ . '/shared/lib.php';

$slug = $_GET['r'] ?? '';
$rest = restaurant_by_slug($slug);
if (!$rest) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="/shared/theme.css">';
    echo '<div style="padding:60px 24px;text-align:center;font-family:system-ui;">';
    echo '<h1 style="font-size:28px;margin-bottom:8px;">Menu introuvable</h1>';
    echo '<p style="color:var(--muted-ink)">Vérifiez le lien QR ou contactez le restaurant.</p>';
    echo '</div>';
    exit;
}

$rid = (int)$rest['id'];

$cats = db()->prepare("SELECT * FROM categories WHERE restaurant_id = ? ORDER BY position, id");
$cats->execute([$rid]);
$categories = $cats->fetchAll();

$ds = db()->prepare("SELECT * FROM dishes WHERE restaurant_id = ? ORDER BY category_id, position, id");
$ds->execute([$rid]);
$dishes = $ds->fetchAll();

$CURRENCY = $rest['currency'] ?: 'DH';
$promos = promotions_active($rid);
[$hoursStatus, $hoursLabel] = hours_status(hours_decode($rest['hours']));
$specials = array_values(array_filter($dishes, fn($d) => (int)$d['is_special'] === 1 && (int)$d['available'] === 1));

$CAT_SUB = [
  'Entrées'  => 'Pour bien commencer votre repas',
  'Plats'    => 'Les classiques de la maison',
  'Pizzas'   => 'Préparées au four à bois',
  'Desserts' => 'Pour terminer en douceur',
  'Boissons' => 'À boire frais ou chaud',
];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($rest['name']) ?> · Menu</title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#2A1324">
<link rel="stylesheet" href="/shared/theme.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { background: var(--light); color: var(--dark); overflow-x: hidden; }

  /* ==== Hours banner (slim top strip) ==== */
  .hours-banner {
    position: fixed; top: 0; left: 0; right: 0; z-index: 60;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 7px 14px;
    background: linear-gradient(90deg, #2A1324, #5A2340);
    color: var(--text-onDark);
    font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
  }
  .hours-banner .dot { width: 8px; height: 8px; border-radius: 50%; box-shadow: 0 0 10px currentColor; }
  .hours-banner.open         { color: #D7F58A; }
  .hours-banner.open .dot    { background: #B6ED5A; }
  .hours-banner.closing_soon { color: #FFC93C; }
  .hours-banner.closing_soon .dot { background: var(--gold); animation: blink 1.4s ease-in-out infinite; }
  .hours-banner.closed       { color: #FFB1A5; }
  .hours-banner.closed .dot  { background: var(--red); }
  @keyframes blink { 50% { opacity: .35; } }

  /* ==== Translucent main header (sits below hours banner) ==== */
  .site-header {
    position: fixed; top: 32px; left: 0; right: 0; z-index: 50;
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 22px;
    color: var(--text-onDark);
    background: transparent;
    transition: background .35s ease, backdrop-filter .35s ease, padding .35s ease, border-color .35s ease;
    border-bottom: 1px solid transparent;
  }
  .site-header.scrolled {
    background: rgba(42,19,36,.95);
    backdrop-filter: saturate(140%) blur(14px);
    -webkit-backdrop-filter: saturate(140%) blur(14px);
    padding: 10px 22px;
    border-bottom-color: rgba(241,172,24,.14);
  }
  .site-header .brand { display: flex; align-items: center; gap: 11px; }
  .site-header .mark {
    width: 38px; height: 38px; border-radius: 10px;
    background: linear-gradient(135deg, var(--gold), var(--amber));
    display: grid; place-items: center; color: var(--dark); font-weight: 800; font-size: 16px;
    box-shadow: 0 10px 24px rgba(241,172,24,.35), inset 0 0 0 1px rgba(255,255,255,.4);
  }
  .site-header .nm { font-weight: 800; font-size: 15px; letter-spacing: -.01em; line-height: 1.1; color: #fff; }
  .site-header .lang { font-size: 10px; color: var(--gold); letter-spacing: .3em; text-transform: uppercase; font-weight: 800; margin-top: 2px; }

  .header-actions { display: flex; align-items: center; gap: 10px; }
  .hdr-icon-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 14px; border-radius: 999px;
    background: rgba(255,255,255,.08); border: 1px solid rgba(241,172,24,.28);
    color: #fff; font-size: 12px; font-weight: 700; letter-spacing: .04em;
    transition: var(--transition);
    position: relative;
  }
  .hdr-icon-btn:hover { background: rgba(255,255,255,.14); border-color: var(--gold); }
  .hdr-icon-btn .cart-count {
    position: absolute; top: -6px; right: -6px;
    width: 18px; height: 18px; border-radius: 50%;
    background: var(--gold); color: var(--dark);
    font-size: 10px; font-weight: 800;
    display: none; place-items: center;
  }
  .hdr-icon-btn.has-items .cart-count { display: grid; }

  /* ==== Hero ==== */
  .menu-hero {
    position: relative;
    min-height: 480px;
    display: grid; place-items: center;
    text-align: center;
    overflow: hidden; isolation: isolate;
    color: var(--text-onDark);
    padding: 150px 24px 90px;
    background-image:
      linear-gradient(90deg, rgba(42,19,36,.92) 0%, rgba(42,19,36,.62) 40%, rgba(42,19,36,.3) 75%, rgba(136,58,97,.14) 100%),
      linear-gradient(120deg, #2A1324 0%, #5A2340 55%, #883A61 100%),
      url('/shared/banner-hero.png');
    background-size: cover; background-position: center; background-repeat: no-repeat;
  }
  .hero-aura-1, .hero-aura-2 { position: absolute; border-radius: 50%; pointer-events: none; z-index: 0;}
  .hero-aura-1 { width: 520px; height: 520px; top: -140px; right: 4%; background: radial-gradient(circle, rgba(241,172,24,.22), transparent 62%); filter: blur(44px); animation: aura1 15s ease-in-out infinite alternate; }
  .hero-aura-2 { width: 460px; height: 460px; bottom: -160px; left: -80px; background: radial-gradient(circle, rgba(136,58,97,.42), transparent 60%); filter: blur(52px); animation: aura2 19s ease-in-out infinite alternate; }
  @keyframes aura1 { from {transform:translate(0,0) scale(1);opacity:.85;} to {transform:translate(-60px,50px) scale(1.18);opacity:1;} }
  @keyframes aura2 { from {transform:translate(0,0) scale(1);opacity:.8;}  to {transform:translate(50px,-40px) scale(1.22);opacity:1;} }
  .menu-hero .inner { position: relative; z-index: 2; max-width: 760px; }
  .hero-eyebrow {
    display: inline-flex; align-items: center; gap: 14px;
    color: var(--gold); font-size: 11px; letter-spacing: .4em; text-transform: uppercase; font-weight: 800;
    margin-bottom: 20px;
  }
  .hero-eyebrow::before, .hero-eyebrow::after { content: ""; width: 42px; height: 1px; background: linear-gradient(90deg, transparent, var(--gold));}
  .hero-eyebrow::after { background: linear-gradient(90deg, var(--gold), transparent); }
  .hero-title { color: var(--text-onDark); font-weight: 800; letter-spacing: -.02em; line-height: 1.08; font-size: clamp(42px, 7vw, 76px); margin-bottom: 16px;}
  .hero-title .accent { background: linear-gradient(135deg, var(--gold), #FFD47A, var(--amber)); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .hero-tag { color: rgba(247,243,236,.84); font-size: clamp(14px, 1.8vw, 17px); max-width: 540px; margin: 0 auto 28px; line-height: 1.6; font-weight: 500; }
  .hero-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

  /* ==== Menu du jour banner ==== */
  .mdj {
    max-width: 1200px; margin: 48px auto 0; padding: 0 24px;
  }
  .mdj-inner {
    background: linear-gradient(135deg, #2A1324, #4a1d3a);
    border-radius: 24px;
    padding: 28px 28px;
    position: relative; overflow: hidden;
    color: var(--text-onDark);
    box-shadow: 0 1px 2px rgba(42,19,36,.04), 0 24px 60px rgba(42,19,36,.22);
    border: 1px solid rgba(241,172,24,.22);
  }
  .mdj-inner::before { content: ""; position: absolute; width: 320px; height: 320px; border-radius: 50%; background: radial-gradient(circle, rgba(241,172,24,.26), transparent 60%); top: -150px; right: -80px; filter: blur(32px); }
  .mdj-head { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; position: relative; z-index: 1; }
  .mdj-head .label {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 6px 14px; border-radius: 999px;
    background: linear-gradient(135deg, var(--gold), var(--amber));
    color: var(--dark); font-size: 11px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase;
    box-shadow: 0 8px 20px rgba(241,172,24,.35);
  }
  .mdj-head h2 { color: #fff; font-size: clamp(22px, 3vw, 30px); font-weight: 800; letter-spacing: -.01em; }
  .mdj-list { display: grid; grid-template-columns: 1fr; gap: 16px; position: relative; z-index: 1; }
  @media (min-width: 720px) { .mdj-list { grid-template-columns: repeat(2, 1fr); } }
  @media (min-width: 1024px) { .mdj-list { grid-template-columns: repeat(3, 1fr); } }
  .mdj-card {
    display: flex; gap: 14px; padding: 14px; border-radius: 16px;
    background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
    color: #fff; transition: var(--transition);
  }
  .mdj-card:hover { background: rgba(255,255,255,.12); transform: translateY(-2px); }
  .mdj-card .photo { width: 72px; height: 72px; border-radius: 12px; overflow: hidden; flex-shrink: 0; background: linear-gradient(135deg, var(--gold), var(--amber)); display:grid; place-items:center; color: var(--dark); font-weight: 800; font-size: 24px; }
  .mdj-card .photo img { width: 100%; height: 100%; object-fit: cover; }
  .mdj-card .nm { font-weight: 800; font-size: 15px; letter-spacing: -.01em; margin-bottom: 4px; }
  .mdj-card .dc { font-size: 12.5px; color: rgba(247,243,236,.7); display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
  .mdj-card .pr { color: var(--gold); font-weight: 800; font-size: 14px; margin-top: 6px; }

  /* ==== Sticky category nav ==== */
  .catnav {
    position: sticky; top: 32px; z-index: 25;
    padding: 14px 20px 10px;
    background: rgba(242,238,230,.94);
    backdrop-filter: saturate(140%) blur(12px);
    -webkit-backdrop-filter: saturate(140%) blur(12px);
    border-bottom: 1px solid var(--border);
  }
  .catnav-inner {
    display: flex; gap: 8px; max-width: 1200px; margin: 0 auto;
    overflow-x: auto; scrollbar-width: none; padding: 2px 2px 4px;
  }
  @media (min-width: 720px) { .catnav-inner { justify-content: center; } }
  .catnav-inner::-webkit-scrollbar { display: none; }
  .cat-tab {
    flex-shrink: 0; padding: 10px 22px; border-radius: 999px;
    background: var(--surface); color: var(--dark);
    font-weight: 700; font-size: 13px; letter-spacing: .01em;
    border: 1px solid var(--border-strong); cursor: pointer; white-space: nowrap;
    transition: all .2s ease;
  }
  .cat-tab:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-1px); }
  .cat-tab.active {
    background: linear-gradient(135deg, var(--gold), var(--amber));
    color: var(--dark); border-color: transparent;
    box-shadow: 0 6px 20px rgba(241,172,24,.32), inset 0 0 0 1px rgba(255,255,255,.4);
  }

  /* ==== Main ==== */
  .menu-wrap { max-width: 1200px; margin: 0 auto; padding: 50px 24px 100px; }
  .search-wrap { max-width: 460px; margin: 0 auto 48px; }
  .search-box { display: flex; align-items: center; gap: 12px; background: var(--surface); padding: 12px 18px; border-radius: 999px; border: 1px solid var(--border-strong); box-shadow: 0 2px 10px rgba(42,19,36,.04); transition: var(--transition);}
  .search-box:focus-within { border-color: var(--accent); box-shadow: 0 0 0 4px rgba(136,58,97,.12);}
  .search-box svg { flex-shrink: 0; color: var(--accent); }
  .search-box input { border: none; outline: none; flex: 1; background: transparent; font-size: 14.5px; font-weight: 500; color: var(--dark);}

  .main_title { text-align: center; margin: 0 auto 36px; opacity: 0; transform: translateY(14px); transition: opacity .7s ease, transform .7s ease;}
  .main_title.in { opacity: 1; transform: none; }
  .main_title > span { display: block; width: 120px; height: 2px; background: rgba(42,19,36,.14); margin: 0 auto; position: relative;}
  .main_title > span em { display: block; width: 60px; height: 2px; background: var(--accent); margin: 0 auto; position: absolute; left: 50%; top: -1px; transform: translateX(-50%);}
  .main_title h2 { font-weight: 800; letter-spacing: -.02em; line-height: 1.1; color: var(--dark); font-size: clamp(30px, 4vw, 44px); margin: 22px 0 10px;}
  .main_title p { color: var(--muted-ink); font-size: 14.5px; font-weight: 500; max-width: 480px; margin: 0 auto; }

  .grid { display: grid; gap: 28px; grid-template-columns: 1fr; margin-bottom: 72px; }
  @media (min-width: 540px)  { .grid { grid-template-columns: repeat(2, 1fr); gap: 24px;} }
  @media (min-width: 900px)  { .grid { grid-template-columns: repeat(3, 1fr); gap: 28px;} }
  @media (min-width: 1200px) { .grid { grid-template-columns: repeat(4, 1fr); gap: 32px;} }

  .menu_item_grid {
    text-align: center; background: var(--surface); border-radius: 18px;
    overflow: hidden; border: 1px solid var(--border);
    box-shadow: 0 1px 2px rgba(42,19,36,.04), 0 10px 26px rgba(42,19,36,.07);
    transition: transform .5s cubic-bezier(.2,.8,.2,1), box-shadow .5s ease, border-color .5s ease;
    opacity: 0; transform: translateY(18px);
    display: flex; flex-direction: column;
    color: inherit; text-decoration: none; position: relative;
  }
  .menu_item_grid.in { opacity: 1; transform: none; }
  .menu_item_grid:hover {
    transform: translateY(-6px);
    box-shadow: 0 2px 4px rgba(42,19,36,.06), 0 24px 56px rgba(42,19,36,.2), 0 0 0 1px rgba(241,172,24,.22);
    border-color: transparent;
  }

  .item-img { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: linear-gradient(135deg, var(--surface-2), var(--track));}
  .item-img img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .9s cubic-bezier(.2,.8,.2,1);}
  .menu_item_grid:hover .item-img img { transform: scale(1.08); }
  .item-img .ph-fallback {
    position: absolute; inset: 0; display: grid; place-items: center;
    background: radial-gradient(circle at 30% 30%, rgba(241,172,24,.14), transparent 55%), radial-gradient(circle at 70% 70%, rgba(136,58,97,.18), transparent 55%), linear-gradient(135deg, #3d1c34, var(--dark));
    color: var(--gold); font-weight: 800; font-size: 38px; letter-spacing: -.03em; text-shadow: 0 2px 20px rgba(241,172,24,.35);
  }
  .item-img::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 50%, rgba(42,19,36,.55) 100%); opacity: 0; transition: opacity .35s ease;}
  .menu_item_grid:hover .item-img::after { opacity: 1; }

  /* Top-right promo pill */
  .promo-pill {
    position: absolute; top: 12px; right: 12px; z-index: 2;
    padding: 5px 11px; border-radius: 999px;
    background: linear-gradient(135deg, var(--red), #ff4d57);
    color: #fff; font-size: 10px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
    box-shadow: 0 6px 16px rgba(204,1,10,.35);
  }
  /* Top-left spice + flags */
  .meta-corner {
    position: absolute; top: 12px; left: 12px; z-index: 2;
    display: flex; gap: 5px;
  }
  .meta-corner .chip {
    padding: 3px 8px; border-radius: 999px;
    background: rgba(255,255,255,.92); color: var(--dark);
    font-size: 11px; font-weight: 800;
    backdrop-filter: blur(6px);
  }
  .meta-corner .chip.spice { background: rgba(242,91,10,.92); color: #fff; }

  .sold-out {
    position: absolute; top: 12px; left: 12px; z-index: 2;
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 11px; border-radius: 999px;
    background: rgba(42,19,36,.92); color: #fff;
    font-size: 10px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
  }
  .sold-out::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--red); box-shadow: 0 0 6px rgba(204,1,10,.6);}

  .view-chip {
    position: absolute; left: 50%; bottom: 16px; z-index: 2;
    transform: translate(-50%, 10px); opacity: 0;
    padding: 7px 16px; border-radius: 999px;
    background: linear-gradient(135deg, var(--gold), var(--amber));
    color: var(--dark); font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
    box-shadow: 0 8px 20px rgba(241,172,24,.35), inset 0 0 0 1px rgba(255,255,255,.4);
    transition: opacity .35s ease, transform .35s ease; white-space: nowrap;
  }
  .menu_item_grid:hover .view-chip { opacity: 1; transform: translate(-50%, 0); }

  .menu_item_grid h3 { font-weight: 800; letter-spacing: -.015em; line-height: 1.2; color: var(--dark); font-size: 18px; margin: 20px 20px 0;}
  .menu_item_grid p { color: var(--muted-ink); font-size: 13.5px; line-height: 1.55; font-weight: 500; margin: 8px 20px 0; display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}

  .price_box {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    margin: 14px 20px 20px; padding-top: 14px;
    border-top: 1px dashed rgba(42,19,36,.1);
  }
  .price_block { display: flex; flex-direction: column; align-items: flex-start; gap: 0; }
  .new_price { color: var(--accent); font-weight: 800; font-size: 20px; letter-spacing: -.01em;}
  .new_price .cur { font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin-left: 3px; opacity: .8; }
  .old_price { color: var(--muted-ink); text-decoration: line-through; font-weight: 600; font-size: 12.5px; letter-spacing: -.01em;}
  .add-btn {
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--dark); color: var(--text-onDark);
    display: grid; place-items: center;
    transition: var(--transition); font-size: 22px; font-weight: 800; line-height: 1;
    border: none; cursor: pointer;
  }
  .add-btn:hover { background: linear-gradient(135deg, var(--gold), var(--amber)); color: var(--dark); transform: scale(1.06);}
  .menu_item_grid.unavailable { opacity: .55; }
  .menu_item_grid.unavailable h3 { text-decoration: line-through; text-decoration-color: rgba(42,19,36,.3); }
  .menu_item_grid.unavailable .add-btn { opacity: .3; pointer-events: none; }

  .empty { text-align: center; padding: 80px 20px; color: var(--muted-ink); max-width: 600px; margin: 0 auto; }
  .empty h3 { color: var(--dark); font-size: 22px; margin-bottom: 8px; font-weight: 800; }
  .cat-section { scroll-margin-top: 110px; }

  /* ==== Cart drawer ==== */
  .cart-overlay { position: fixed; inset: 0; z-index: 90; background: rgba(42,19,36,.55); opacity: 0; pointer-events: none; transition: opacity .3s ease;}
  .cart-overlay.open { opacity: 1; pointer-events: auto; }
  .cart {
    position: fixed; top: 0; right: 0; bottom: 0; width: 100%; max-width: 420px; z-index: 100;
    background: var(--light); color: var(--dark);
    transform: translateX(100%); transition: transform .4s cubic-bezier(.2,.8,.2,1);
    display: flex; flex-direction: column; box-shadow: -20px 0 60px rgba(42,19,36,.3);
  }
  .cart.open { transform: translateX(0); }
  .cart-head {
    padding: 20px 22px; display: flex; align-items: center; justify-content: space-between;
    background: var(--dark); color: var(--text-onDark);
  }
  .cart-head .ttl { font-weight: 800; font-size: 18px; letter-spacing: -.01em; }
  .cart-head .close-btn {
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(255,255,255,.08); color: #fff;
    display: grid; place-items: center; font-size: 18px; font-weight: 800; cursor: pointer;
  }
  .cart-head .close-btn:hover { background: rgba(255,255,255,.18); }
  .cart-body { flex: 1; overflow-y: auto; padding: 16px 20px; }
  .cart-empty { text-align: center; padding: 60px 20px; color: var(--muted-ink); }
  .cart-empty h3 { font-weight: 800; color: var(--dark); margin-bottom: 8px; }
  .cart-row { display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--border); }
  .cart-row:last-child { border-bottom: none; }
  .cart-row .pho { width: 58px; height: 58px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: var(--surface-2); display: grid; place-items: center; color: var(--gold); font-weight: 800; }
  .cart-row .pho img { width: 100%; height: 100%; object-fit: cover; }
  .cart-row .info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
  .cart-row .nm { font-weight: 800; font-size: 14px; letter-spacing: -.01em; }
  .cart-row .pr { color: var(--accent); font-weight: 800; font-size: 13.5px; }
  .cart-row .pr .ol { text-decoration: line-through; color: var(--muted-ink); font-weight: 600; margin-right: 6px; font-size: 11.5px;}
  .qty { display: flex; align-items: center; gap: 6px; margin-top: 2px; }
  .qty button {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--surface); border: 1px solid var(--border-strong); color: var(--dark);
    font-weight: 800; cursor: pointer; transition: var(--transition);
  }
  .qty button:hover { background: var(--dark); color: var(--text-onDark); border-color: var(--dark); }
  .qty .n { font-weight: 800; min-width: 20px; text-align: center; }
  .cart-row .rm {
    width: 32px; height: 32px; border-radius: 10px;
    background: var(--surface-2); color: var(--muted-ink);
    border: 1px solid var(--border);
    display: grid; place-items: center;
    cursor: pointer; transition: var(--transition);
    align-self: flex-start; flex-shrink: 0;
  }
  .cart-row .rm:hover { background: var(--bad-bg); color: var(--bad-ink); border-color: var(--bad-ink); transform: scale(1.06);}
  .cart-row .rm svg { width: 15px; height: 15px; }
  .cart-row { position: relative; }
  .cart-row .rm-abs { position: absolute; top: 14px; right: 0; }

  .clear-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    width: 100%; padding: 11px 16px; border-radius: 999px;
    background: transparent; color: var(--bad-ink);
    border: 1px solid rgba(204,1,10,.3);
    font-weight: 700; font-size: 13px; letter-spacing: .04em;
    margin-top: 10px; transition: var(--transition); cursor: pointer;
  }
  .clear-btn:hover { background: var(--bad-bg); border-color: var(--bad-ink); }
  .clear-btn svg { width: 15px; height: 15px; }

  .cart-foot {
    padding: 18px 22px 22px; background: var(--surface); border-top: 1px solid var(--border);
  }
  .total-row { display: flex; justify-content: space-between; align-items: baseline; font-weight: 800; margin-bottom: 14px; }
  .total-row .lbl { font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted-ink);}
  .total-row .val { font-size: 22px; color: var(--accent); letter-spacing: -.01em; }
  .wa-btn {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; padding: 14px 20px; border-radius: 999px;
    background: #25D366; color: #fff; font-weight: 800; letter-spacing: .02em;
    box-shadow: 0 8px 24px rgba(37,211,102,.3);
    transition: var(--transition);
  }
  .wa-btn:hover { background: #1EBE5A; transform: translateY(-1px); box-shadow: 0 12px 32px rgba(37,211,102,.45); }

  /* ==== Footer ==== */
  .menu-foot { background: var(--dark); color: var(--text-onDark); padding: 54px 24px 36px; text-align: center; position: relative; overflow: hidden;}
  .menu-foot::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--gold), transparent);}
  .menu-foot .meta { display: flex; justify-content: center; gap: 20px; margin-bottom: 20px; font-size: 13px; color: rgba(247,243,236,.7); flex-wrap: wrap; }
  .menu-foot .meta a { color: var(--gold); font-weight: 700; }
  .menu-foot .brand-line { font-weight: 800; font-size: 20px; letter-spacing: -.01em; margin-bottom: 6px;}
  .menu-foot .brand-line .gold { color: var(--gold); }
  .menu-foot .sub-line { font-size: 10px; letter-spacing: .4em; text-transform: uppercase; color: rgba(247,243,236,.45); font-weight: 800;}

  @media (prefers-reduced-motion: reduce) {
    .hero-aura-1, .hero-aura-2 { animation: none !important; }
    .main_title, .menu_item_grid { opacity: 1; transform: none; transition: none;}
    .menu_item_grid:hover { transform: none; }
    .menu_item_grid:hover .item-img img { transform: none;}
  }
</style>
</head>
<body>

<div class="hours-banner <?= e($hoursStatus) ?>" id="hoursBanner">
  <span class="dot"></span>
  <span><?= e($hoursLabel) ?><?php if ($rest['address']): ?> · <?= e($rest['address']) ?><?php endif; ?></span>
</div>

<header class="site-header" id="siteHeader">
  <div class="brand">
    <div class="mark"><?= e(mb_substr($rest['name'], 0, 1)) ?></div>
    <div>
      <div class="nm"><?= e($rest['name']) ?></div>
      <div class="lang">Menu Digital</div>
    </div>
  </div>
  <div class="header-actions">
    <a class="hdr-icon-btn" href="/reservations.php?r=<?= e($rest['slug']) ?>">Réserver</a>
    <button class="hdr-icon-btn" id="openCart" type="button" aria-label="Panier">
      Panier<span class="cart-count" id="cartCount">0</span>
    </button>
  </div>
</header>

<section class="menu-hero" id="top">
  <span class="hero-aura-1" aria-hidden="true"></span>
  <span class="hero-aura-2" aria-hidden="true"></span>
  <div class="inner">
    <div class="hero-eyebrow">La Carte</div>
    <h1 class="hero-title">Notre <span class="accent">Menu</span></h1>
    <p class="hero-tag"><?= e($rest['tagline'] ?: 'Une sélection de plats préparés chaque jour avec passion.') ?></p>
    <div class="hero-actions">
      <a href="#menu" class="btn-primary">Découvrir la carte</a>
      <a href="/reservations.php?r=<?= e($rest['slug']) ?>" class="btn-outline-dark">Réserver une table</a>
    </div>
  </div>
</section>

<?php if ($specials): ?>
<section class="mdj">
  <div class="mdj-inner">
    <div class="mdj-head">
      <span class="label">✦ Menu du jour</span>
      <h2>Les suggestions du chef</h2>
    </div>
    <div class="mdj-list">
      <?php foreach ($specials as $s):
        $p = dish_promo_price($s, $promos);
      ?>
        <a class="mdj-card" href="/dish.php?id=<?= (int)$s['id'] ?>">
          <div class="photo">
            <?php if ($s['photo']): ?><img src="<?= e(UPLOADS_URL . '/' . $s['photo']) ?>" alt=""><?php else: ?><?= e(mb_substr($rest['name'],0,1)) ?><?php endif; ?>
          </div>
          <div style="min-width:0;">
            <div class="nm"><?= e($s['name']) ?></div>
            <div class="dc"><?= e($s['description']) ?></div>
            <div class="pr"><?= number_format($p['final'], 0, ',', ' ') ?> <?= e($CURRENCY) ?><?php if ($p['pct'] > 0): ?> <span style="color:rgba(247,243,236,.5);text-decoration:line-through;font-weight:600;font-size:12px;"><?= number_format($p['original'],0,',',' ') ?></span><?php endif; ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (empty($categories) || empty($dishes)): ?>
  <div class="empty"><h3>Menu bientôt disponible</h3><p>Le restaurant n'a pas encore publié de plats.</p></div>
<?php else: ?>

<nav class="catnav" id="catnav">
  <div class="catnav-inner" id="catTabs">
    <button class="cat-tab active" data-cat="all">Tout</button>
    <?php foreach ($categories as $c): ?>
      <button class="cat-tab" data-cat="c<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
    <?php endforeach; ?>
  </div>
</nav>

<main class="menu-wrap" id="menu">
  <div class="search-wrap">
    <label class="search-box">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
      <input id="q" type="search" placeholder="Rechercher un plat…" autocomplete="off">
    </label>
  </div>

  <?php foreach ($categories as $c):
    $items = array_values(array_filter($dishes, fn($d) => (int)$d['category_id'] === (int)$c['id']));
    if (!$items) continue;
    $sub = $CAT_SUB[$c['name']] ?? null;
  ?>
    <section class="cat-section" data-cat-id="c<?= (int)$c['id'] ?>" id="cat-<?= (int)$c['id'] ?>">
      <div class="main_title reveal">
        <span><em></em></span>
        <h2><?= e($c['name']) ?></h2>
        <?php if ($sub): ?><p><?= e($sub) ?></p><?php endif; ?>
      </div>

      <div class="grid">
        <?php foreach ($items as $dIdx => $d):
          $p = dish_promo_price($d, $promos);
          $spice = (int)$d['spice'];
        ?>
          <article class="menu_item_grid reveal <?= $d['available'] ? '' : 'unavailable' ?>"
                   data-id="<?= (int)$d['id'] ?>"
                   data-name="<?= e(mb_strtolower($d['name'])) ?>"
                   style="transition-delay: <?= min($dIdx * 50, 300) ?>ms;">
            <a class="item-img" href="/dish.php?id=<?= (int)$d['id'] ?>" style="display:block;color:inherit;">
              <?php if ($d['photo']): ?>
                <img src="<?= e(UPLOADS_URL . '/' . $d['photo']) ?>" alt="<?= e($d['name']) ?>" loading="lazy">
              <?php else: ?>
                <div class="ph-fallback"><?= e(mb_substr($rest['name'], 0, 1)) ?></div>
              <?php endif; ?>
              <?php if (!$d['available']): ?>
                <span class="sold-out">Épuisé</span>
              <?php else: ?>
                <div class="meta-corner">
                  <?php if ($spice > 0): ?><span class="chip spice">🌶<?= str_repeat('·', max(0, $spice - 1)) ?></span><?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($p['pct'] > 0 && $d['available']): ?>
                <span class="promo-pill">−<?= $p['pct'] ?>%</span>
              <?php endif; ?>
              <span class="view-chip">Voir le plat →</span>
            </a>
            <a href="/dish.php?id=<?= (int)$d['id'] ?>" style="color:inherit;text-decoration:none;">
              <h3><?= e($d['name']) ?></h3>
              <?php if (!empty($d['description'])): ?><p><?= e($d['description']) ?></p><?php endif; ?>
            </a>
            <div class="price_box">
              <div class="price_block">
                <span class="new_price"><?= number_format($p['final'], 0, ',', ' ') ?><span class="cur"><?= e($CURRENCY) ?></span></span>
                <?php if ($p['pct'] > 0): ?><span class="old_price"><?= number_format($p['original'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></span><?php endif; ?>
              </div>
              <button class="add-btn"
                      type="button"
                      data-add
                      data-id="<?= (int)$d['id'] ?>"
                      data-name="<?= e($d['name']) ?>"
                      data-price="<?= e($p['final']) ?>"
                      data-original="<?= e($p['original']) ?>"
                      data-photo="<?= e($d['photo'] ?? '') ?>"
                      aria-label="Ajouter au panier">+</button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</main>
<?php endif; ?>

<footer class="menu-foot">
  <div class="meta">
    <?php if ($rest['phone']): ?><span>📞 <a href="tel:+<?= e(wa_number($rest['phone'])) ?>">+<?= e(wa_number($rest['phone'])) ?></a></span><?php endif; ?>
    <?php if ($rest['address']): ?><span>📍 <?= e($rest['address']) ?></span><?php endif; ?>
    <span>🕐 <?= e($hoursLabel) ?></span>
  </div>
  <div class="brand-line">Propulsé par <span class="gold">Wevtex</span> Menu Digital</div>
  <div class="sub-line">Carte renouvelée régulièrement</div>
</footer>

<!-- Cart drawer -->
<div class="cart-overlay" id="cartOverlay" aria-hidden="true"></div>
<aside class="cart" id="cartDrawer" aria-label="Panier">
  <div class="cart-head">
    <div class="ttl">Votre commande</div>
    <button class="close-btn" id="closeCart" aria-label="Fermer">×</button>
  </div>
  <div class="cart-body" id="cartBody"></div>
  <div class="cart-foot" id="cartFoot" style="display:none;">
    <div class="total-row"><span class="lbl">Total</span><span class="val" id="cartTotal">0 <?= e($CURRENCY) ?></span></div>
    <a class="wa-btn" id="waBtn" href="#">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11 11 0 0 0 3.6 17.4L2 22l4.7-1.5A11 11 0 1 0 20.5 3.5Zm-8.5 17a9 9 0 0 1-4.6-1.3l-.3-.2-2.8.9.9-2.7-.2-.3A9 9 0 1 1 12 20.5Zm5-6.6c-.3-.2-1.6-.8-1.9-.9-.3-.1-.4-.1-.6.1l-.9 1.1c-.2.2-.3.2-.6.1-.3-.1-1.1-.4-2-1.2-.7-.6-1.2-1.4-1.4-1.7-.1-.3 0-.4.1-.5l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.1c-.2-.5-.5-.5-.6-.5h-.6c-.2 0-.5.1-.7.4-.3.3-1 1-1 2.4 0 1.4 1 2.8 1.1 3 .1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.6-.7 1.8-1.3.2-.6.2-1.2.2-1.3-.1-.1-.3-.2-.6-.3Z"/></svg>
      Commander via WhatsApp
    </a>
    <button class="clear-btn" id="clearCart" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
      Vider le panier
    </button>
  </div>
</aside>

<script>
  // ==== Header scroll ====
  const header = document.getElementById('siteHeader');
  window.addEventListener('scroll', () => header.classList.toggle('scrolled', window.scrollY > 40), { passive: true });

  // ==== Reveal on scroll ====
  const io = ('IntersectionObserver' in window) ? new IntersectionObserver((ents) => {
    ents.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }) : null;
  document.querySelectorAll('.reveal').forEach(el => { if (io) io.observe(el); else el.classList.add('in'); });

  // ==== Category filter + search ====
  const tabs = document.querySelectorAll('.cat-tab');
  const sections = document.querySelectorAll('.cat-section');
  const q = document.getElementById('q');
  const applyFilter = () => {
    const activeTab = document.querySelector('.cat-tab.active');
    const key = activeTab ? activeTab.dataset.cat : 'all';
    const v = (q.value || '').trim().toLowerCase();
    sections.forEach(s => {
      const inCat = (key === 'all' || s.dataset.catId === key);
      s.style.display = inCat ? '' : 'none';
      if (!inCat) return;
      let any = false;
      s.querySelectorAll('.menu_item_grid').forEach(d => {
        const match = !v || (d.dataset.name || '').includes(v);
        d.style.display = match ? '' : 'none';
        if (match) any = true;
      });
      if (!any) s.style.display = 'none';
    });
  };
  tabs.forEach(t => t.addEventListener('click', () => {
    tabs.forEach(x => x.classList.remove('active'));
    t.classList.add('active');
    applyFilter();
    if (t.dataset.cat !== 'all') {
      const target = document.querySelector('[data-cat-id="' + t.dataset.cat + '"]');
      if (target) window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 110, behavior: 'smooth' });
    }
  }));
  q.addEventListener('input', () => {
    if (q.value.trim()) { tabs.forEach(x => x.classList.remove('active')); tabs[0].classList.add('active'); }
    applyFilter();
  });

  // ==== Cart (localStorage) ====
  const RID = <?= $rid ?>;
  const CURRENCY = <?= json_encode($CURRENCY) ?>;
  const UPLOADS = <?= json_encode(UPLOADS_URL) ?>;
  const PHONE = <?= json_encode(wa_number($rest['phone'] ?? '')) ?>;
  const REST_NAME = <?= json_encode($rest['name']) ?>;
  const CART_KEY = 'cart_' + RID;

  const loadCart = () => { try { return JSON.parse(localStorage.getItem(CART_KEY)) || {}; } catch { return {}; } };
  const saveCart = (c) => { try { localStorage.setItem(CART_KEY, JSON.stringify(c)); } catch {} };

  const cartOverlay = document.getElementById('cartOverlay');
  const cartDrawer  = document.getElementById('cartDrawer');
  const cartBody    = document.getElementById('cartBody');
  const cartFoot    = document.getElementById('cartFoot');
  const cartTotalEl = document.getElementById('cartTotal');
  const cartCount   = document.getElementById('cartCount');
  const openCartBtn = document.getElementById('openCart');
  const waBtn       = document.getElementById('waBtn');

  const openCart  = () => { cartDrawer.classList.add('open'); cartOverlay.classList.add('open'); document.body.style.overflow = 'hidden'; };
  const closeCart = () => { cartDrawer.classList.remove('open'); cartOverlay.classList.remove('open'); document.body.style.overflow = ''; };
  openCartBtn.addEventListener('click', openCart);
  document.getElementById('closeCart').addEventListener('click', closeCart);
  cartOverlay.addEventListener('click', closeCart);

  const fmt = (n) => Number(n).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' ' + CURRENCY;
  const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  const toastLite = (msg) => {
    const t = document.createElement('div');
    t.textContent = msg;
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--dark);color:var(--text-onDark);padding:10px 20px;border-radius:999px;font-weight:700;font-size:13px;z-index:9999;box-shadow:0 16px 36px rgba(42,19,36,.3);transition:opacity .3s ease;';
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 1600);
  };

  function renderCart() {
    const cart = loadCart();
    const ids = Object.keys(cart);
    cartBody.innerHTML = '';
    let count = 0, total = 0;
    if (!ids.length) {
      cartBody.innerHTML = '<div class="cart-empty"><h3>Panier vide</h3><p>Ajoutez des plats depuis la carte.</p></div>';
      cartFoot.style.display = 'none';
      openCartBtn.classList.remove('has-items');
      cartCount.textContent = '0';
      return;
    }
    cartFoot.style.display = '';
    openCartBtn.classList.add('has-items');
    ids.forEach(id => {
      const it = cart[id];
      count += it.qty;
      total += it.price * it.qty;
      const row = document.createElement('div');
      row.className = 'cart-row';
      row.innerHTML = `
        <div class="pho">${it.photo ? `<img src="${UPLOADS}/${it.photo}" alt="">` : REST_NAME.charAt(0)}</div>
        <div class="info">
          <div class="nm">${escapeHtml(it.name)}</div>
          <div class="pr">${it.original && it.original > it.price ? `<span class="ol">${fmt(it.original)}</span>` : ''}${fmt(it.price * it.qty)}</div>
          <div class="qty">
            <button data-dec="${id}" aria-label="Moins">−</button>
            <span class="n">${it.qty}</span>
            <button data-inc="${id}" aria-label="Plus">+</button>
          </div>
        </div>
        <button class="rm rm-abs" data-rm="${id}" aria-label="Supprimer ${escapeHtml(it.name)}" title="Supprimer ce plat">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
        </button>`;
      cartBody.appendChild(row);
    });
    cartCount.textContent = String(count);
    cartTotalEl.textContent = fmt(total);

    // Build the WhatsApp message
    let msg = `Bonjour ${REST_NAME}, je souhaite commander :\n`;
    ids.forEach(id => { const it = cart[id]; msg += `• ${it.qty}× ${it.name} — ${fmt(it.price * it.qty)}\n`; });
    msg += `\nTotal : ${fmt(total)}\nMerci !`;
    waBtn.href = PHONE ? ('https://wa.me/' + PHONE + '?text=' + encodeURIComponent(msg))
                      : ('https://wa.me/?text=' + encodeURIComponent(msg));
  }

  document.addEventListener('click', (ev) => {
    const add = ev.target.closest('[data-add]');
    if (add) {
      const id = add.dataset.id;
      const cart = loadCart();
      if (!cart[id]) cart[id] = {
        id, name: add.dataset.name,
        price: Number(add.dataset.price), original: Number(add.dataset.original || add.dataset.price),
        photo: add.dataset.photo || '', qty: 0,
      };
      cart[id].qty++;
      saveCart(cart); renderCart(); openCart();
      return;
    }
    const dec = ev.target.closest('[data-dec]'); if (dec) { const cart = loadCart(); const id = dec.dataset.dec; if (cart[id]) { cart[id].qty--; if (cart[id].qty <= 0) delete cart[id]; saveCart(cart); renderCart(); } return; }
    const inc = ev.target.closest('[data-inc]'); if (inc) { const cart = loadCart(); const id = inc.dataset.inc; if (cart[id]) { cart[id].qty++; saveCart(cart); renderCart(); } return; }
    const rm  = ev.target.closest('[data-rm]');
    if (rm) {
      const row = rm.closest('.cart-row');
      const cart = loadCart(); const id = rm.dataset.rm;
      if (!cart[id]) return;
      const name = cart[id].name;
      if (row) {
        row.style.transition = 'opacity .25s ease, transform .25s ease, max-height .3s ease, padding .3s ease, border .3s ease';
        row.style.opacity = '0'; row.style.transform = 'translateX(30px)';
        setTimeout(() => { delete cart[id]; saveCart(cart); renderCart(); toastLite(name + ' retiré'); }, 220);
      } else { delete cart[id]; saveCart(cart); renderCart(); }
      return;
    }
  });

  // "Vider le panier" (clear-all)
  document.getElementById('clearCart').addEventListener('click', () => {
    const cart = loadCart();
    if (!Object.keys(cart).length) return;
    if (!confirm('Vider complètement le panier ?')) return;
    try { localStorage.removeItem(CART_KEY); } catch {}
    renderCart();
    toastLite('Panier vidé');
  });

  renderCart();
</script>
</body></html>

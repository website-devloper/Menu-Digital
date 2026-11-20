<?php
require __DIR__ . '/shared/config.php';
require __DIR__ . '/shared/lib.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Plat invalide.'); }

$st = db()->prepare('
    SELECT d.*, c.name AS cat_name, c.id AS cat_id, r.name AS rest_name, r.slug AS rest_slug, r.tagline, r.currency, r.phone AS rest_phone, r.hours AS rest_hours
    FROM dishes d
    JOIN restaurants r ON r.id = d.restaurant_id
    LEFT JOIN categories c ON c.id = d.category_id
    WHERE d.id = ?');
$st->execute([$id]);
$d = $st->fetch();
if (!$d) { http_response_code(404); exit('Plat introuvable.'); }

$rid = (int)$d['restaurant_id'];
$CURRENCY = $d['currency'] ?: 'DH';

// Gallery photos (cover first; falls back to the dish photo column)
$g = db()->prepare('SELECT photo FROM dish_photos WHERE dish_id = ? ORDER BY is_cover DESC, position, id');
$g->execute([$id]);
$gallery = array_map(fn($r) => $r['photo'], $g->fetchAll());
if (!$gallery && !empty($d['photo'])) $gallery = [$d['photo']];
$heroPhoto = $gallery[0] ?? null;

// Promotion?
$promos = promotions_active($rid);
$pp = dish_promo_price($d, $promos);

// Flags + spice
$flags = flags_to_list($d['flags']);
$spice = (int)$d['spice'];

// Related dishes
$rel = db()->prepare('SELECT id, name, price, photo FROM dishes WHERE restaurant_id = ? AND category_id = ? AND id != ? AND available = 1 ORDER BY position LIMIT 3');
$rel->execute([$rid, $d['cat_id'], $id]);
$related = $rel->fetchAll();

$backUrl = '/menu.php?r=' . rawurlencode($d['rest_slug']) . '#cat-' . (int)$d['cat_id'];
[$hoursStatus, $hoursLabel] = hours_status(hours_decode($d['rest_hours']));

$personalMessage = 'Bonjour, je souhaite commander : ' . $d['name'] . ' (' . number_format($pp['final'], 0, ',', ' ') . ' ' . $CURRENCY . ').';
$waHref = wa_url(wa_number($d['rest_phone'] ?? ''), $personalMessage);
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= e($d['name']) ?> · <?= e($d['rest_name']) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#2A1324">
<meta name="description" content="<?= e(mb_substr($d['description'] ?? '', 0, 160)) ?>">
<link rel="stylesheet" href="/shared/theme.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  body { background: var(--light); color: var(--dark); overflow-x: hidden; }

  .site-header { position: fixed; top: 0; left: 0; right: 0; z-index: 50; display: flex; align-items: center; justify-content: space-between; padding: 14px 22px; color: var(--text-onDark); background: transparent; transition: background .35s ease, backdrop-filter .35s ease, padding .35s ease, border-color .35s ease; border-bottom: 1px solid transparent;}
  .site-header.scrolled { background: rgba(42,19,36,.96); backdrop-filter: saturate(140%) blur(14px); -webkit-backdrop-filter: saturate(140%) blur(14px); padding: 10px 22px; border-bottom-color: rgba(241,172,24,.14);}
  .site-header .brand { display: flex; align-items: center; gap: 11px;}
  .site-header .mark { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, var(--gold), var(--amber)); display: grid; place-items: center; color: var(--dark); font-weight: 800; font-size: 16px; box-shadow: 0 10px 24px rgba(241,172,24,.35), inset 0 0 0 1px rgba(255,255,255,.4);}
  .site-header .nm { font-weight: 800; font-size: 15px; letter-spacing: -.01em; line-height: 1.1; color: #fff;}
  .site-header .lang { font-size: 10px; color: var(--gold); letter-spacing: .3em; text-transform: uppercase; font-weight: 800; margin-top: 2px;}
  .back-chip { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 999px; background: rgba(255,255,255,.08); border: 1px solid rgba(241,172,24,.28); color: #fff; font-size: 12px; font-weight: 700; letter-spacing: .04em; transition: var(--transition);}
  .back-chip:hover { background: rgba(255,255,255,.14); border-color: var(--gold); }

  /* ==== Hero gallery ==== */
  .dish-hero { position: relative; min-height: 520px; padding: 110px 20px 70px; display: grid; place-items: end center; text-align: center; overflow: hidden; color: var(--text-onDark);}
  .dish-hero .slides { position: absolute; inset: 0; z-index: 0;}
  .dish-hero .slide {
    position: absolute; inset: 0; background-size: cover; background-position: center;
    opacity: 0; transition: opacity 1s ease, transform 8s ease;
    transform: scale(1.05);
  }
  .dish-hero .slide.on { opacity: 1; transform: scale(1.12); }
  .dish-hero::before { content: ""; position: absolute; inset: 0; z-index: 1; background: linear-gradient(180deg, rgba(42,19,36,.55) 0%, rgba(42,19,36,.72) 50%, rgba(42,19,36,.95) 100%), radial-gradient(ellipse at top, rgba(241,172,24,.14), transparent 60%);}
  .dish-hero.no-photo { background: linear-gradient(120deg, #2A1324 0%, #5A2340 55%, #883A61 100%); min-height: 380px;}
  .dish-hero.no-photo::before { background: radial-gradient(ellipse at top, rgba(241,172,24,.14), transparent 65%);}

  .hero-inner { position: relative; z-index: 2; max-width: 820px; padding-bottom: 20px; }
  .hero-eyebrow { display: inline-flex; align-items: center; gap: 12px; color: var(--gold); font-size: 11px; letter-spacing: .4em; text-transform: uppercase; font-weight: 800; margin-bottom: 20px;}
  .hero-eyebrow::before, .hero-eyebrow::after { content: ""; width: 36px; height: 1px; background: linear-gradient(90deg, transparent, var(--gold));}
  .hero-eyebrow::after { background: linear-gradient(90deg, var(--gold), transparent); }
  .dish-hero h1 { color: #fff; font-size: clamp(36px, 6vw, 60px); font-weight: 800; letter-spacing: -.02em; line-height: 1.05; margin-bottom: 14px; text-shadow: 0 6px 24px rgba(0,0,0,.4);}

  .price-row {
    display: inline-flex; align-items: baseline; gap: 12px;
    padding: 6px 20px;
  }
  .price-row .hero-price { font-weight: 800; font-size: 28px; letter-spacing: -.01em; color: var(--gold);}
  .price-row .hero-price .cur { font-size: 13px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; opacity: .85; margin-left: 2px;}
  .price-row .old-price { font-weight: 700; font-size: 16px; color: rgba(247,243,236,.55); text-decoration: line-through;}
  .price-row .promo-tag {
    padding: 4px 11px; border-radius: 999px;
    background: linear-gradient(135deg, var(--red), #ff4d57);
    color: #fff; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
    box-shadow: 0 6px 16px rgba(204,1,10,.35);
  }

  .hero-tags { display: flex; gap: 7px; justify-content: center; flex-wrap: wrap; margin-top: 18px;}
  .hero-tags .tag { padding: 5px 13px; border-radius: 999px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.22); color: #fff; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; backdrop-filter: blur(6px);}

  /* Thumbnail strip */
  .thumbs { display: flex; gap: 10px; justify-content: center; margin-top: 22px; }
  .thumbs button { width: 56px; height: 56px; border-radius: 10px; overflow: hidden; border: 2px solid rgba(255,255,255,.35); background: transparent; cursor: pointer; padding: 0; transition: var(--transition);}
  .thumbs button:hover { transform: scale(1.05); border-color: var(--gold); }
  .thumbs button.active { border-color: var(--gold); box-shadow: 0 0 0 2px rgba(241,172,24,.35);}
  .thumbs img { width: 100%; height: 100%; object-fit: cover; }

  /* ==== Body ==== */
  .dish-wrap { max-width: 900px; margin: -24px auto 0; padding: 0 20px 40px; position: relative; z-index: 3;}
  .card-main { background: var(--surface); border-radius: 24px; padding: 32px 28px; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(42,19,36,.04), 0 24px 60px rgba(42,19,36,.14); margin-bottom: 36px;}
  @media (min-width: 720px) { .card-main { padding: 40px 48px;} }

  .meta-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px dashed rgba(42,19,36,.1);}
  .meta-row .left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap;}
  .cat-pill { display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border-radius: 999px; background: var(--surface-2); color: var(--accent); font-size: 11px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase; border: 1px solid rgba(136,58,97,.18);}
  .cat-pill::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--accent);}
  .status-pill { display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border-radius: 999px; background: var(--ok-bg); color: var(--ok-ink); font-size: 11px; font-weight: 800; letter-spacing: .18em; text-transform: uppercase;}
  .status-pill::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--green-2); box-shadow: 0 0 6px rgba(24,158,1,.5);}
  .status-pill.bad { background: var(--bad-bg); color: var(--bad-ink);}
  .status-pill.bad::before { background: var(--red); box-shadow: 0 0 6px rgba(204,1,10,.5);}
  .share-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; background: transparent; border: 1px solid var(--border-strong); color: var(--dark); font-weight: 700; font-size: 12.5px; transition: var(--transition);}
  .share-btn:hover { border-color: var(--accent); color: var(--accent);}

  .lead { font-size: 17px; line-height: 1.7; color: var(--dark); font-weight: 500; margin-bottom: 20px;}

  .block-title { font-size: 11px; letter-spacing: .24em; text-transform: uppercase; color: var(--accent); font-weight: 800; margin: 28px 0 14px; display: flex; align-items: center; gap: 10px;}
  .block-title::after { content: ""; flex: 1; height: 1px; background: rgba(136,58,97,.18);}

  /* Dietary flag chips */
  .flag-grid { display: flex; flex-wrap: wrap; gap: 8px;}
  .flag-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 999px;
    background: rgba(114,184,0,.1); color: #3f6b00;
    border: 1px solid rgba(114,184,0,.26);
    font-size: 12.5px; font-weight: 700;
  }
  .flag-chip.warn { background: rgba(241,172,24,.14); color: #8b5a00; border-color: rgba(241,172,24,.32);}
  .flag-chip.nut  { background: rgba(242,91,10,.12);  color: #a23c05; border-color: rgba(242,91,10,.3);}

  /* Spice meter */
  .spice-meter { display: flex; align-items: center; gap: 10px;}
  .spice-meter .dots { display: flex; gap: 6px;}
  .spice-meter .dot { width: 18px; height: 18px; border-radius: 6px; background: var(--surface-2); border: 1px solid var(--border-strong);}
  .spice-meter .dot.on { background: linear-gradient(135deg, #ff6a00, var(--red)); border-color: transparent; box-shadow: 0 4px 12px rgba(242,91,10,.25);}
  .spice-meter .lbl { font-size: 13px; font-weight: 700; color: var(--muted-ink);}

  /* Promo banner inside the card */
  .promo-banner {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 18px;
    background: linear-gradient(135deg, rgba(204,1,10,.08), rgba(242,91,10,.08));
    border: 1px solid rgba(204,1,10,.18);
    border-radius: 14px; margin-bottom: 20px;
  }
  .promo-banner .emoji { font-size: 24px;}
  .promo-banner .info { flex: 1; }
  .promo-banner .info strong { display: block; font-size: 15px; font-weight: 800; color: var(--bad-ink); margin-bottom: 2px;}
  .promo-banner .info small { font-size: 12.5px; color: var(--muted-ink); font-weight: 500;}

  /* Facts */
  .facts { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-top: 10px;}
  @media (min-width: 640px) { .facts { grid-template-columns: repeat(4, 1fr);} }
  .fact { padding: 14px 16px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 14px;}
  .fact .lbl { font-size: 10.5px; letter-spacing: .16em; text-transform: uppercase; color: var(--muted-ink); font-weight: 800;}
  .fact .val { font-size: 15px; font-weight: 800; letter-spacing: -.01em; color: var(--dark); margin-top: 2px;}

  /* CTAs */
  .cta-row { display: grid; grid-template-columns: 1fr; gap: 10px; margin-top: 28px; padding-top: 24px; border-top: 1px dashed rgba(42,19,36,.1);}
  @media (min-width: 540px) { .cta-row { grid-template-columns: 1fr 1fr 1fr;} }
  .cta-row .add-cart {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 14px 20px; border-radius: 999px;
    background: var(--dark); color: var(--text-onDark);
    font-weight: 800; letter-spacing: .02em; font-size: 14px;
    transition: var(--transition); cursor: pointer; border: none;
  }
  .cta-row .add-cart:hover { background: linear-gradient(135deg, var(--gold), var(--amber)); color: var(--dark); transform: translateY(-1px);}
  .cta-row .wa-cta {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    padding: 14px 20px; border-radius: 999px;
    background: #25D366; color: #fff; font-weight: 800; letter-spacing: .02em; font-size: 14px;
    transition: var(--transition);
    box-shadow: 0 8px 20px rgba(37,211,102,.28);
  }
  .cta-row .wa-cta:hover { background: #1EBE5A; transform: translateY(-1px);}
  .cta-row .btn-ghost { padding: 14px 24px; font-size: 13px; letter-spacing: .04em;}

  /* Related */
  .related { max-width: 900px; margin: 0 auto 60px; padding: 0 20px;}
  .related h2 { text-align: center; font-weight: 800; letter-spacing: -.02em; font-size: clamp(22px, 3.6vw, 32px); margin-bottom: 28px;}
  .related h2::after { content: ""; display: block; width: 44px; height: 3px; background: var(--accent); margin: 10px auto 0; border-radius: 2px;}
  .related-grid { display: grid; grid-template-columns: 1fr; gap: 18px;}
  @media (min-width: 640px) { .related-grid { grid-template-columns: repeat(3, 1fr);} }
  .rel-card { background: var(--surface); border-radius: 16px; overflow: hidden; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(42,19,36,.04), 0 8px 24px rgba(42,19,36,.06); transition: transform .4s cubic-bezier(.2,.8,.2,1), box-shadow .4s ease; display: block; color: inherit;}
  .rel-card:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(42,19,36,.14);}
  .rel-photo { aspect-ratio: 16 / 10; overflow: hidden; position: relative; background: linear-gradient(135deg, #3d1c34, var(--dark));}
  .rel-photo img { width: 100%; height: 100%; object-fit: cover; transition: transform .8s ease;}
  .rel-card:hover .rel-photo img { transform: scale(1.08);}
  .rel-photo .fallback { position: absolute; inset: 0; display: grid; place-items: center; color: var(--gold); font-weight: 800; font-size: 28px;}
  .rel-body { padding: 14px 18px 18px; text-align: center;}
  .rel-name { font-weight: 800; font-size: 15px; letter-spacing: -.01em; margin-bottom: 4px;}
  .rel-price { color: var(--accent); font-weight: 800; font-size: 15px;}

  .menu-foot { background: var(--dark); color: var(--text-onDark); padding: 50px 24px 32px; text-align: center; position: relative;}
  .menu-foot::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 2px; background: linear-gradient(90deg, transparent, var(--gold), transparent);}
  .menu-foot .brand-line { font-weight: 800; font-size: 20px; letter-spacing: -.01em; margin-bottom: 6px;}
  .menu-foot .brand-line .gold { color: var(--gold);}
  .menu-foot .sub-line { font-size: 10px; letter-spacing: .4em; text-transform: uppercase; color: rgba(247,243,236,.45); font-weight: 800;}

  .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px); background: var(--dark); color: var(--text-onDark); padding: 10px 20px; border-radius: 999px; font-weight: 700; font-size: 13px; opacity: 0; transition: all .3s ease; pointer-events: none; z-index: 9999; box-shadow: 0 16px 36px rgba(42,19,36,.3);}
  .toast.show { opacity: 1; transform: translateX(-50%) translateY(0);}

  @media (prefers-reduced-motion: reduce) { .dish-hero .slide { animation: none !important; transition: opacity .3s ease !important; transform: none !important;} }
</style>
</head>
<body>

<header class="site-header" id="siteHeader">
  <div class="brand">
    <div class="mark"><?= e(mb_substr($d['rest_name'], 0, 1)) ?></div>
    <div><div class="nm"><?= e($d['rest_name']) ?></div><div class="lang">Menu Digital</div></div>
  </div>
  <a href="<?= e($backUrl) ?>" class="back-chip">← Menu</a>
</header>

<section class="dish-hero <?= $heroPhoto ? '' : 'no-photo' ?>" id="hero">
  <?php if ($gallery): ?>
    <div class="slides" aria-hidden="true">
      <?php foreach ($gallery as $i => $g): ?>
        <div class="slide <?= $i === 0 ? 'on' : '' ?>" style="background-image:url('<?= e(UPLOADS_URL . '/' . $g) ?>')"></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="hero-inner">
    <div class="hero-eyebrow"><?= e($d['cat_name'] ?? 'Notre carte') ?></div>
    <h1><?= e($d['name']) ?></h1>
    <div class="price-row">
      <?php if ($pp['pct'] > 0): ?>
        <span class="old-price"><?= number_format($pp['original'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></span>
        <span class="hero-price"><?= number_format($pp['final'], 0, ',', ' ') ?><span class="cur"><?= e($CURRENCY) ?></span></span>
        <span class="promo-tag">−<?= (int)$pp['pct'] ?>%</span>
      <?php else: ?>
        <span class="hero-price"><?= number_format($pp['final'], 0, ',', ' ') ?><span class="cur"><?= e($CURRENCY) ?></span></span>
      <?php endif; ?>
    </div>

    <?php if ($flags || $spice > 0 || (int)$d['is_special'] === 1): ?>
      <div class="hero-tags">
        <?php if ((int)$d['is_special'] === 1): ?><span class="tag">★ Menu du jour</span><?php endif; ?>
        <?php foreach ($flags as $f): [$lbl, $em] = flag_label($f); ?><span class="tag"><?= e(($em ? $em . ' ' : '') . $lbl) ?></span><?php endforeach; ?>
        <?php if ($spice > 0): ?><span class="tag">🌶<?= str_repeat('·', max(0, $spice - 1)) ?> Épicé</span><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (count($gallery) > 1): ?>
      <div class="thumbs">
        <?php foreach ($gallery as $i => $g): ?>
          <button type="button" class="<?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>"><img src="<?= e(UPLOADS_URL . '/' . $g) ?>" alt=""></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<div class="dish-wrap">
  <div class="card-main">
    <div class="meta-row">
      <div class="left">
        <a class="cat-pill" href="<?= e('/menu.php?r=' . rawurlencode($d['rest_slug']) . '#cat-' . (int)$d['cat_id']) ?>"><?= e($d['cat_name'] ?? 'Sans catégorie') ?></a>
        <?php if ((int)$d['available'] === 1): ?>
          <span class="status-pill"><?= e($hoursLabel) ?></span>
        <?php else: ?>
          <span class="status-pill bad">Épuisé aujourd'hui</span>
        <?php endif; ?>
      </div>
      <button class="share-btn" id="shareBtn" type="button">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        Partager
      </button>
    </div>

    <?php if ($pp['promo']): ?>
      <div class="promo-banner">
        <div class="emoji">🎉</div>
        <div class="info">
          <strong><?= e($pp['promo']['name']) ?> — <?= (int)$pp['pct'] ?>% de réduction</strong>
          <small>
            <?php
              $w = [];
              if (!empty($pp['promo']['start_time']) || !empty($pp['promo']['end_time'])) {
                $w[] = 'de ' . ($pp['promo']['start_time'] ?: '00:00') . ' à ' . ($pp['promo']['end_time'] ?: '00:00');
              }
              echo e('Prix réduit actif ' . ($w ? implode(' ', $w) : 'aujourd\'hui') . '.');
            ?>
          </small>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($d['description'])): ?><p class="lead"><?= e($d['description']) ?></p><?php endif; ?>

    <?php if ($flags): ?>
      <div class="block-title">Allergènes &amp; régimes</div>
      <div class="flag-grid">
        <?php foreach ($flags as $f): [$lbl, $em] = flag_label($f);
          $cls = in_array($f, ['gluten','lactose']) ? 'warn' : ($f === 'nuts' ? 'nut' : '');
        ?>
          <span class="flag-chip <?= e($cls) ?>"><?= e($em) ?> <?= e($lbl) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($spice > 0): ?>
      <div class="block-title">Niveau d'épice</div>
      <div class="spice-meter">
        <div class="dots">
          <?php for ($i = 1; $i <= 3; $i++): ?>
            <span class="dot <?= $i <= $spice ? 'on' : '' ?>"></span>
          <?php endfor; ?>
        </div>
        <span class="lbl"><?= ['', 'Doux', 'Moyen', 'Fort'][$spice] ?? 'Moyen' ?></span>
      </div>
    <?php endif; ?>

    <div class="block-title">Détails</div>
    <div class="facts">
      <div class="fact"><div class="lbl">Catégorie</div><div class="val"><?= e($d['cat_name'] ?? '—') ?></div></div>
      <div class="fact"><div class="lbl">Prix</div><div class="val" style="color:var(--accent);"><?= number_format($pp['final'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></div></div>
      <div class="fact"><div class="lbl">État</div><div class="val" style="color:<?= $d['available'] ? 'var(--green-2)' : 'var(--bad-ink)' ?>;"><?= $d['available'] ? 'Disponible' : 'Épuisé' ?></div></div>
      <div class="fact"><div class="lbl">Préparation</div><div class="val">À la commande</div></div>
    </div>

    <div class="cta-row">
      <?php if ((int)$d['available'] === 1): ?>
        <button class="add-cart" type="button"
                data-add
                data-id="<?= (int)$d['id'] ?>"
                data-name="<?= e($d['name']) ?>"
                data-price="<?= e($pp['final']) ?>"
                data-original="<?= e($pp['original']) ?>"
                data-photo="<?= e($d['photo'] ?? '') ?>">+ Ajouter au panier</button>
        <a class="wa-cta" href="<?= e($waHref) ?>" target="_blank">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11 11 0 0 0 3.6 17.4L2 22l4.7-1.5A11 11 0 1 0 20.5 3.5Zm-8.5 17a9 9 0 0 1-4.6-1.3l-.3-.2-2.8.9.9-2.7-.2-.3A9 9 0 1 1 12 20.5Z"/></svg>
          Commander ce plat
        </a>
      <?php else: ?>
        <button class="btn-ghost" style="opacity:.5;cursor:not-allowed;" disabled>Indisponible</button>
      <?php endif; ?>
      <a class="btn-ghost" href="<?= e($backUrl) ?>">← Retour au menu</a>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="related">
      <h2>Vous aimerez aussi</h2>
      <div class="related-grid">
        <?php foreach ($related as $r): ?>
          <a class="rel-card" href="/dish.php?id=<?= (int)$r['id'] ?>">
            <div class="rel-photo">
              <?php if ($r['photo']): ?><img src="<?= e(UPLOADS_URL . '/' . $r['photo']) ?>" alt="<?= e($r['name']) ?>" loading="lazy"><?php else: ?><div class="fallback"><?= e(mb_substr($d['rest_name'], 0, 1)) ?></div><?php endif; ?>
            </div>
            <div class="rel-body">
              <div class="rel-name"><?= e($r['name']) ?></div>
              <div class="rel-price"><?= number_format((float)$r['price'], 0, ',', ' ') ?> <?= e($CURRENCY) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<footer class="menu-foot">
  <div class="brand-line">Propulsé par <span class="gold">Wevtex</span> Menu Digital</div>
  <div class="sub-line"><?= e($d['rest_name']) ?></div>
</footer>

<div class="toast" id="toast"></div>

<script>
  const header = document.getElementById('siteHeader');
  window.addEventListener('scroll', () => header.classList.toggle('scrolled', window.scrollY > 40), { passive: true });

  const toast = (msg) => { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(window.__tt); window.__tt = setTimeout(() => t.classList.remove('show'), 2000); };

  document.getElementById('shareBtn').addEventListener('click', async () => {
    const payload = { title: document.title, text: document.title, url: location.href };
    if (navigator.share) { try { await navigator.share(payload); return; } catch {} }
    try { await navigator.clipboard.writeText(location.href); toast('Lien copié dans le presse-papier'); } catch { toast('Impossible de partager'); }
  });

  // Gallery thumbnails
  document.querySelectorAll('.thumbs button').forEach(b => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.thumbs button').forEach(x => x.classList.remove('active'));
      b.classList.add('active');
      const idx = Number(b.dataset.slide);
      document.querySelectorAll('.dish-hero .slide').forEach((s, i) => s.classList.toggle('on', i === idx));
    });
  });

  // Auto-rotate gallery every 5s
  const slides = document.querySelectorAll('.dish-hero .slide');
  const thumbs = document.querySelectorAll('.thumbs button');
  if (slides.length > 1) {
    let idx = 0;
    setInterval(() => {
      idx = (idx + 1) % slides.length;
      slides.forEach((s, i) => s.classList.toggle('on', i === idx));
      thumbs.forEach((t, i) => t.classList.toggle('active', i === idx));
    }, 5000);
  }

  // Add to cart (shared localStorage key with menu.php)
  const RID = <?= $rid ?>;
  const CART_KEY = 'cart_' + RID;
  document.querySelectorAll('[data-add]').forEach(btn => {
    btn.addEventListener('click', () => {
      let cart = {};
      try { cart = JSON.parse(localStorage.getItem(CART_KEY)) || {}; } catch {}
      const id = btn.dataset.id;
      if (!cart[id]) cart[id] = { id, name: btn.dataset.name, price: Number(btn.dataset.price), original: Number(btn.dataset.original || btn.dataset.price), photo: btn.dataset.photo, qty: 0 };
      cart[id].qty++;
      try { localStorage.setItem(CART_KEY, JSON.stringify(cart)); } catch {}
      toast('Ajouté au panier');
    });
  });
</script>
</body></html>

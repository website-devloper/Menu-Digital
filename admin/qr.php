<?php
require __DIR__ . '/../shared/config.php';
$user = require_auth();
$rest = current_restaurant($user);
$menuUrl = base_url() . '/menu.php?r=' . rawurlencode($rest['slug']);

$PAGE_TITLE = 'QR Code';
$ACTIVE     = 'qr';
require __DIR__ . '/../shared/layout.php';
?>

<div class="page-head">
  <div>
    <h1>QR Code du menu</h1>
    <div class="sub">Imprimez-le sur vos tables : chaque scan ouvre votre menu digital.</div>
  </div>
</div>

<div style="display:grid;grid-template-columns: 1fr 1fr; gap: 24px;">
  <div class="card" style="padding: 24px; text-align: center;">
    <div id="qrBox" style="margin: 10px auto 18px; padding: 24px; display: inline-block; background:#fff; border:1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-soft);"></div>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
      <button class="btn-primary" id="dlPng">Télécharger PNG</button>
      <button class="btn-ghost" id="dlSvg">Télécharger SVG</button>
      <button class="btn-ghost" onclick="window.print()">Imprimer</button>
    </div>
  </div>

  <div class="card" style="padding: 24px;">
    <div style="font-size:12px;color:var(--muted-ink);letter-spacing:.08em;text-transform:uppercase;font-weight:700;margin-bottom:6px;">Lien du menu public</div>
    <div style="display:flex;gap:8px;align-items:center;">
      <input class="input" id="menuUrl" value="<?= e($menuUrl) ?>" readonly>
      <button class="btn-ghost" onclick="navigator.clipboard.writeText(document.getElementById('menuUrl').value); toast('Lien copié', 'ok');">Copier</button>
    </div>
    <div style="margin-top:14px;">
      <a class="btn-ghost" href="<?= e($menuUrl) ?>" target="_blank">Ouvrir le menu ↗</a>
    </div>

    <hr style="margin: 22px 0; border: none; border-top: 1px solid var(--border);">
    <h3 style="font-size:15px;font-weight:800;margin-bottom:6px;">Conseil impression</h3>
    <p style="font-size:13.5px;color:var(--muted-ink);line-height:1.55;">
      Le PNG exporté est en haute résolution (1024×1024). Ajoutez votre logo et le nom du restaurant au-dessus avant l'impression sur les chevalets de table.
    </p>
  </div>
</div>

<style>
@media print {
  body * { visibility: hidden !important; }
  #qrBox, #qrBox * { visibility: visible !important; }
  #qrBox { position: absolute; left: 50%; top: 20%; transform: translateX(-50%); border:none !important; box-shadow:none !important; }
  .sidebar, .page-head, .toolbar { display:none !important; }
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  const url = <?= json_encode($menuUrl) ?>;
  const slug = <?= json_encode($rest['slug']) ?>;

  // Render HD canvas (qrcodejs draws both canvas + img)
  const box = document.getElementById('qrBox');
  new QRCode(box, { text: url, width: 320, height: 320, correctLevel: QRCode.CorrectLevel.H });

  document.getElementById('dlPng').addEventListener('click', () => {
    const canvas = box.querySelector('canvas');
    if (!canvas) return;
    // Upscale for print
    const scale = 1024 / canvas.width;
    const hd = document.createElement('canvas');
    hd.width = 1024; hd.height = 1024;
    const ctx = hd.getContext('2d');
    ctx.imageSmoothingEnabled = false;
    ctx.fillStyle = '#fff'; ctx.fillRect(0,0,1024,1024);
    ctx.drawImage(canvas, 0, 0, 1024, 1024);
    const a = document.createElement('a');
    a.href = hd.toDataURL('image/png');
    a.download = 'menu-qr-' + slug + '.png';
    a.click();
  });

  // Build a clean SVG by hand from the known URL (lightweight; uses the canvas module values)
  document.getElementById('dlSvg').addEventListener('click', () => {
    const canvas = box.querySelector('canvas');
    if (!canvas) return;
    const w = canvas.width;
    const img = canvas.getContext('2d').getImageData(0, 0, w, w);
    const d = img.data;
    const cells = [];
    // Detect module size by scanning first black run on top row
    let mod = 1; let lastDark = -1;
    for (let x = 0; x < w; x++) { if (d[(x) * 4] < 128) { lastDark = x; break; } }
    // Fallback: just dump pixels
    const parts = [`<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 ${w} ${w}' shape-rendering='crispEdges'><rect width='100%' height='100%' fill='#fff'/>`];
    for (let y = 0; y < w; y++) {
      for (let x = 0; x < w; x++) {
        const i = (y * w + x) * 4;
        if (d[i] < 128 && d[i+1] < 128 && d[i+2] < 128) {
          parts.push(`<rect x='${x}' y='${y}' width='1' height='1' fill='#000'/>`);
        }
      }
    }
    parts.push('</svg>');
    const blob = new Blob([parts.join('')], {type: 'image/svg+xml'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'menu-qr-' + slug + '.svg';
    a.click();
  });
</script>

<?php require __DIR__ . '/../shared/layout_foot.php';

<?php
require __DIR__ . '/../shared/config.php';
$user = require_auth();
$rest = current_restaurant($user);

$PAGE_TITLE = 'Scanner QR';
$ACTIVE     = 'scan';
require __DIR__ . '/../shared/layout.php';
?>

<div class="page-head">
  <div>
    <h1>Scanner un QR</h1>
    <div class="sub">Ouvrez la caméra pour lire un QR et accéder au menu correspondant.</div>
  </div>
</div>

<div class="card" style="padding: 20px; max-width: 520px;">
  <div id="reader" style="width:100%; border-radius: var(--radius-md); overflow:hidden; background:#000; min-height: 280px;"></div>
  <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;">
    <button class="btn-primary" id="btnStart">Démarrer la caméra</button>
    <button class="btn-ghost"   id="btnStop" disabled>Arrêter</button>
  </div>
  <div id="result" style="margin-top:16px;padding:14px;border-radius: var(--radius-md);background: var(--surface-2);border:1px solid var(--border);display:none;">
    <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted-ink);font-weight:700;margin-bottom:6px;">Résultat</div>
    <div id="resultUrl" style="word-break:break-all;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;margin-bottom:10px;"></div>
    <a id="openResult" class="btn-primary" target="_blank">Ouvrir ce menu ↗</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
  let scanner = null;

  const start = async () => {
    document.getElementById('btnStart').disabled = true;
    document.getElementById('btnStop').disabled  = false;
    scanner = new Html5Qrcode('reader');
    try {
      await scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, onFound, () => {});
    } catch (e) {
      toast('Caméra inaccessible: ' + e.message, 'bad');
      document.getElementById('btnStart').disabled = false;
      document.getElementById('btnStop').disabled  = true;
    }
  };
  const stop = async () => {
    if (scanner) { try { await scanner.stop(); } catch {} await scanner.clear(); scanner = null; }
    document.getElementById('btnStart').disabled = false;
    document.getElementById('btnStop').disabled  = true;
  };
  const onFound = async (txt) => {
    document.getElementById('result').style.display = 'block';
    document.getElementById('resultUrl').textContent = txt;
    document.getElementById('openResult').href = txt;
    toast('QR détecté !', 'ok');
    await stop();
  };
  document.getElementById('btnStart').addEventListener('click', start);
  document.getElementById('btnStop').addEventListener('click', stop);
</script>

<?php require __DIR__ . '/../shared/layout_foot.php';

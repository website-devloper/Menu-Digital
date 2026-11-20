  </main>
</div>

<!-- Tiny toast system shared across admin pages -->
<div id="toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);opacity:0;transition:all .25s ease;background:var(--dark);color:var(--text-onDark);padding:10px 18px;border-radius:var(--radius-full);box-shadow:var(--shadow-hover);font-weight:600;font-size:14px;z-index:9999;pointer-events:none;"></div>
<script>
window.toast = (msg, kind) => {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.background = kind === 'ok' ? '#189E01' : (kind === 'bad' ? '#CC010A' : 'var(--dark)');
  t.style.opacity = '1'; t.style.transform = 'translateX(-50%) translateY(0)';
  clearTimeout(window.__tt);
  window.__tt = setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(20px)'; }, 2200);
};
// Surface ?ok= / ?err= query flashes
(() => {
  const p = new URLSearchParams(location.search);
  if (p.get('ok'))  toast(decodeURIComponent(p.get('ok')), 'ok');
  if (p.get('err')) toast(decodeURIComponent(p.get('err')), 'bad');
})();
</script>
</body></html>

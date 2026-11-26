// Podpis prstem – plátno v dolním panelu, výsledek jako PNG data URL
import { sheet, esc } from './ui.js';
import { t } from './i18n.js';

export function signaturePad(title) {
  return sheet(`
    <h3>${esc(title)}</h3>
    <div class="sig-wrap"><canvas class="sig-canvas"></canvas><div class="sig-hint">${esc(t('sigHint'))}</div></div>
    <div class="row" style="margin-top:10px">
      <button class="btn" data-clear>${esc(t('sigClear'))}</button>
      <button class="btn" data-no>${esc(t('cancel'))}</button>
      <button class="btn primary" data-yes>${esc(t('ok'))}</button>
    </div>
  `, (dlg, close) => {
    const cv = dlg.querySelector('canvas');
    const hint = dlg.querySelector('.sig-hint');
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const ctx = cv.getContext('2d');
    let drawn = false;
    let last = null;

    const size = () => {
      const w = cv.parentElement.clientWidth;
      const h = Math.min(260, Math.round(w * 0.5));
      cv.style.width = w + 'px';
      cv.style.height = h + 'px';
      cv.width = Math.round(w * ratio);
      cv.height = Math.round(h * ratio);
      ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
      ctx.lineWidth = 2.4;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = '#111827';
    };
    requestAnimationFrame(size);

    const pos = (e) => {
      const r = cv.getBoundingClientRect();
      return { x: e.clientX - r.left, y: e.clientY - r.top };
    };
    cv.addEventListener('pointerdown', (e) => {
      e.preventDefault();
      cv.setPointerCapture(e.pointerId);
      last = pos(e);
      ctx.beginPath();
      ctx.arc(last.x, last.y, 1.1, 0, Math.PI * 2);
      ctx.fillStyle = '#111827';
      ctx.fill();
      drawn = true;
      hint.hidden = true;
    });
    cv.addEventListener('pointermove', (e) => {
      if (!last) return;
      e.preventDefault();
      const p = pos(e);
      ctx.beginPath();
      ctx.moveTo(last.x, last.y);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      last = p;
    });
    const up = () => { last = null; };
    cv.addEventListener('pointerup', up);
    cv.addEventListener('pointercancel', up);

    dlg.querySelector('[data-clear]').onclick = () => {
      ctx.clearRect(0, 0, cv.width, cv.height);
      drawn = false;
      hint.hidden = false;
    };
    dlg.querySelector('[data-no]').onclick = () => close(undefined);
    dlg.querySelector('[data-yes]').onclick = () => close(drawn ? exportPng(cv) : null);
  });
}

/** Ořízne prázdné okraje a zmenší na max. 600 px šířky */
function exportPng(cv) {
  const ctx = cv.getContext('2d');
  const { width: w, height: h } = cv;
  const px = ctx.getImageData(0, 0, w, h).data;
  let x0 = w, y0 = h, x1 = 0, y1 = 0;
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      if (px[(y * w + x) * 4 + 3] > 0) {
        if (x < x0) x0 = x; if (x > x1) x1 = x;
        if (y < y0) y0 = y; if (y > y1) y1 = y;
      }
    }
  }
  if (x1 < x0) return null;
  const pad = 8;
  x0 = Math.max(0, x0 - pad); y0 = Math.max(0, y0 - pad);
  x1 = Math.min(w - 1, x1 + pad); y1 = Math.min(h - 1, y1 + pad);
  const cw = x1 - x0 + 1, ch = y1 - y0 + 1;
  const scale = Math.min(1, 600 / cw);
  const out = document.createElement('canvas');
  out.width = Math.max(1, Math.round(cw * scale));
  out.height = Math.max(1, Math.round(ch * scale));
  out.getContext('2d').drawImage(cv, x0, y0, cw, ch, 0, 0, out.width, out.height);
  return out.toDataURL('image/png');
}

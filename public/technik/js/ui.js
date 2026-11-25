// Drobné pomocníky pro UI.

import { t, getLang } from './i18n.js';

export function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

export function fmtDate(iso, withTime = true) {
  if (!iso) return '—';
  const d = new Date(iso);
  if (isNaN(d)) return '—';
  const opts = withTime
    ? { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    : { day: 'numeric', month: 'numeric', year: 'numeric' };
  return d.toLocaleString(getLang() === 'pl' ? 'pl-PL' : 'cs-CZ', opts);
}

/** ISO (UTC) → hodnota pro <input type=datetime-local> v místním čase */
export function toLocalInput(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d)) return '';
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** hodnota z <input type=datetime-local> → ISO s časovou zónou */
export function fromLocalInput(v) {
  if (!v) return null;
  const d = new Date(v);
  return isNaN(d) ? null : d.toISOString();
}

export function uuid() {
  if (crypto.randomUUID) return crypto.randomUUID();
  return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
    (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16));
}

let toastTimer;
export function toast(msg, isError = false, ms = 3000) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast' + (isError ? ' err' : '');
  el.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.hidden = true; }, ms);
}

export function chip(status) {
  return `<span class="chip ${esc(status)}">${esc(t('status.' + status))}</span>`;
}

/** Spodní panel s obsahem; vrací Promise s hodnotou předanou do close() */
export function sheet(html, bind) {
  const dlg = document.getElementById('sheet');
  dlg.innerHTML = html;
  return new Promise((resolve) => {
    const close = (value) => { dlg.close(); resolve(value); };
    dlg.onclick = (e) => { if (e.target === dlg) close(undefined); };
    dlg.oncancel = () => resolve(undefined);
    if (bind) bind(dlg, close);
    dlg.showModal();
  });
}

export function confirmSheet(text) {
  return sheet(`
    <p>${esc(text)}</p>
    <div class="row"><button class="btn" data-no>${esc(t('cancel'))}</button><button class="btn primary" data-yes>${esc(t('ok'))}</button></div>
  `, (dlg, close) => {
    dlg.querySelector('[data-no]').onclick = () => close(false);
    dlg.querySelector('[data-yes]').onclick = () => close(true);
  });
}

export function promptSheet(title, value = '') {
  return sheet(`
    <h3>${esc(title)}</h3>
    <label class="field"><textarea data-in>${esc(value)}</textarea></label>
    <div class="row"><button class="btn" data-no>${esc(t('cancel'))}</button><button class="btn primary" data-yes>${esc(t('ok'))}</button></div>
  `, (dlg, close) => {
    dlg.querySelector('[data-no]').onclick = () => close(null);
    dlg.querySelector('[data-yes]').onclick = () => close(dlg.querySelector('[data-in]').value.trim());
  });
}

export function debounce(fn, ms) {
  let h;
  const d = (...args) => { clearTimeout(h); h = setTimeout(() => fn(...args), ms); };
  d.flush = (...args) => { clearTimeout(h); return fn(...args); };
  return d;
}

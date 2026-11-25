// PAP Technik – mobilní aplikace pro techniky (PWA).
// Obrazovky: přihlášení → moje zakázky → detail → záznam návštěvy.
// Vše se ukládá nejdřív do telefonu a odesílá frontou (sync.js), takže práce jde i bez signálu.

import { kv, outbox } from './db.js';
import { api, setToken, ApiError, NetworkError } from './api.js';
import { enqueue, stats, pendingFor, discard, retryFailed, kick, syncEvents } from './sync.js';
import { t, setLang, getLang } from './i18n.js';
import { shrink } from './photo.js';
import { pushSupported, pushPermission, enablePush, refreshPush, disablePushForLogout, needsInstallForPush } from './push.js';
import {
  esc, fmtDate, toLocalInput, fromLocalInput, uuid, toast, chip, sheet, confirmSheet, promptSheet, debounce,
} from './ui.js';

const APP_VERSION = '1.2.0';
const $app = document.getElementById('app');
const state = { me: null, leaveHandlers: [] };
const imageUrls = new Map(); // uuid přílohy → objectURL

// ---------------------------------------------------------------- start

async function boot() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js', { scope: './' }).catch(() => {});
  }

  const auth = await kv.get('auth');
  setLang((await kv.get('lang')) || auth?.me?.locale || 'cs');

  if (auth?.token) {
    setToken(auth.token);
    state.me = auth.me;
  }

  bindChrome();
  window.addEventListener('hashchange', route);
  syncEvents.addEventListener('change', updateSyncPill);
  syncEvents.addEventListener('auth', onAuthExpired);
  syncEvents.addEventListener('conflict', () => toast(t('conflict'), true, 5000));
  syncEvents.addEventListener('done', onSyncDone);
  window.addEventListener('online', updateOnline);
  window.addEventListener('offline', updateOnline);
  updateOnline();

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', (e) => {
      if (e.data?.type === 'open' && e.data.url) {
        const hash = e.data.url.split('#')[1];
        if (hash) location.hash = '#' + hash;
      }
      if (e.data?.type === 'push' && parseRoute().view === 'list') refreshList(false);
    });
  }

  route();
  kick(1000);
  if (state.me) refreshPush();
}

function bindChrome() {
  document.getElementById('btn-back').onclick = () => history.back();
  document.getElementById('btn-menu').onclick = openMenu;
  document.getElementById('sync-pill').onclick = openSync;
}

function updateOnline() {
  const bar = document.getElementById('offline-bar');
  bar.hidden = navigator.onLine;
  bar.textContent = t('offline');
}

async function updateSyncPill() {
  const pill = document.getElementById('sync-pill');
  const s = await stats();
  if (s.failed.length) {
    pill.hidden = false;
    pill.className = 'sync-pill err';
    pill.textContent = '⚠ ' + s.failed.length;
  } else if (s.pending) {
    pill.hidden = false;
    pill.className = 'sync-pill';
    pill.textContent = '⇅ ' + s.pending;
  } else {
    pill.hidden = true;
  }
}

let lastDone = 0;
async function onSyncDone() {
  // po odeslání fronty obnovit aktuální obrazovku ze serveru (max. 1× za 5 s)
  const s = await stats();
  if (s.pending === 0 && Date.now() - lastDone > 5000 && !document.querySelector('[data-editing]')) {
    lastDone = Date.now();
    const r = parseRoute();
    if (r.view === 'list') refreshList(true);
  }
}

function onAuthExpired() {
  toast(t('sessionExpired'), true, 6000);
  state.me = null;
  setToken(null);
  kv.get('auth').then((a) => kv.set('auth', { ...(a || {}), token: null }));
  location.hash = '#/login';
}

function setTop(title, back = false) {
  document.getElementById('topbar').hidden = false;
  document.getElementById('topbar-title').textContent = title;
  document.getElementById('btn-back').hidden = !back;
  updateSyncPill();
}

// ---------------------------------------------------------------- router

function parseRoute() {
  const h = location.hash.replace(/^#\/?/, '');
  if (h === 'login') return { view: 'login' };
  let m = h.match(/^t\/(\d+)\/visit$/);
  if (m) return { view: 'visit', id: m[1] };
  m = h.match(/^t\/(\d+)$/);
  if (m) return { view: 'detail', id: m[1] };
  return { view: 'list' };
}

async function route() {
  // uložit rozpracovaný formulář před odchodem
  const handlers = state.leaveHandlers.splice(0);
  for (const fn of handlers) { try { await fn(); } catch { /* nic */ } }

  const r = parseRoute();
  if (!state.me && r.view !== 'login') { location.replace('#/login'); return; }
  window.scrollTo(0, 0);

  if (r.view === 'login') return renderLogin();
  if (r.view === 'detail') return renderDetail(r.id);
  if (r.view === 'visit') return renderVisit(r.id);
  return renderList();
}

// ---------------------------------------------------------------- přihlášení

function renderLogin() {
  document.getElementById('topbar').hidden = true;
  $app.innerHTML = `
    <form class="login card" id="login-form" autocomplete="on">
      <img class="logo" src="icons/icon-192.png" alt="">
      <h1>${esc(t('appName'))}</h1>
      <label class="field"><span>${esc(t('email'))}</span>
        <input type="email" name="email" required autocomplete="username" inputmode="email"></label>
      <label class="field"><span>${esc(t('password'))}</span>
        <input type="password" name="password" required autocomplete="current-password"></label>
      <div class="banner err" id="login-err" hidden></div>
      <button class="btn primary" style="width:100%" type="submit">${esc(t('login'))}</button>
      <p class="muted small" style="text-align:center;margin-top:16px">
        <button type="button" class="btn small" id="lang-switch">${getLang() === 'cs' ? 'Polski' : 'Čeština'}</button>
      </p>
    </form>`;

  document.getElementById('lang-switch').onclick = async () => {
    setLang(getLang() === 'cs' ? 'pl' : 'cs');
    await kv.set('lang', getLang());
    updateOnline();
    renderLogin();
  };

  const form = document.getElementById('login-form');
  form.onsubmit = async (e) => {
    e.preventDefault();
    const err = document.getElementById('login-err');
    err.hidden = true;
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      const res = await api.login(form.email.value.trim(), form.password.value);
      if (!res.user.roles.includes('technik')) {
        err.textContent = t('notTechnician');
        err.hidden = false;
        return;
      }
      setToken(res.token);
      state.me = res.user;
      await kv.set('auth', { token: res.token, me: res.user });
      if (!(await kv.get('lang'))) setLang(res.user.locale || 'cs');
      location.hash = '#/';
      kick(200);
      refreshPush();
    } catch (ex) {
      err.textContent = ex instanceof NetworkError ? t('offline') : t('loginFailed');
      err.hidden = false;
    } finally {
      btn.disabled = false;
    }
  };
}

// ---------------------------------------------------------------- seznam zakázek

async function renderList() {
  setTop(t('myTickets'));
  const cached = await kv.get('tickets');
  if (cached) await drawList(cached.items, true);
  else $app.innerHTML = '<div class="spinner"></div>';
  refreshList(!cached);
}

async function refreshList(showErrors) {
  try {
    const res = await api.myTickets();
    await kv.set('tickets', { items: res.data, at: Date.now() });
    if (parseRoute().view === 'list') drawList(res.data, false);
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) return onAuthExpired();
    if (showErrors && parseRoute().view === 'list') {
      const cached = await kv.get('tickets');
      if (!cached) $app.innerHTML = `<div class="empty">${esc(e instanceof NetworkError ? t('offline') : t('loadFailed'))}</div>`;
    }
  }
}

async function drawList(items, fromCache) {
  const { items: queue } = await stats();
  const pendingIds = new Set(queue.map((i) => String(i.ticketId)));
  const overrides = (await kv.get('statusOverrides')) || {};

  const order = { urgent: 0, high: 1, normal: 2, low: 3 };
  const sorted = [...items].sort((a, b) =>
    (order[a.priority] ?? 2) - (order[b.priority] ?? 2)
    || (a.due_at || '9999').localeCompare(b.due_at || '9999')
    || b.id - a.id);

  const cards = sorted.map((tk) => {
    const status = overrides[tk.id] || tk.status;
    const loc = tk.location;
    return `
      <a class="card ticket prio-${esc(tk.priority)}" href="#/t/${tk.id}">
        <div class="ticket-head"><span class="ticket-num">${esc(tk.number || '#' + tk.id)}</span>${chip(status)}</div>
        <div class="ticket-title">${esc(tk.title)}</div>
        <div class="ticket-loc">${loc ? esc(loc.code + ' · ' + loc.name) + '<br>' + esc([loc.street, loc.city].filter(Boolean).join(', ')) : ''}</div>
        <div class="ticket-meta">
          ${tk.due_at ? `<span>⏱ ${esc(fmtDate(tk.due_at))}</span>` : ''}
          ${tk.priority !== 'normal' ? `<span>${esc(t('prio.' + tk.priority))}</span>` : ''}
          ${pendingIds.has(String(tk.id)) ? `<span class="chip pending">⇅ ${esc(t('waitingUpload'))}</span>` : ''}
        </div>
      </a>`;
  }).join('');

  const askPush = pushSupported() && pushPermission() === 'default' && !(await kv.get('pushDismissed'));
  const iosHint = !pushSupported() && needsInstallForPush() && !(await kv.get('pushDismissed'));

  $app.innerHTML = `
    ${fromCache && !navigator.onLine ? `<div class="banner warn">${esc(t('offlineCached'))}</div>` : ''}
    ${askPush ? `<div class="banner warn" id="push-ask">🔔 ${esc(t('pushAsk'))}
      <div class="row" style="margin-top:8px"><button class="btn small" id="push-later">${esc(t('later'))}</button><button class="btn small primary" id="push-on">${esc(t('pushEnable'))}</button></div></div>` : ''}
    ${iosHint ? `<div class="banner warn" id="push-ask">🔔 ${esc(t('pushIos'))}
      <div class="row" style="margin-top:8px"><button class="btn small" id="push-later">${esc(t('ok'))}</button></div></div>` : ''}
    ${cards || `<div class="empty">${esc(t('noTickets'))}</div>`}
    <div class="actions"><button class="btn" id="btn-refresh">↻ ${esc(t('refresh'))}</button></div>`;
  document.getElementById('btn-refresh').onclick = () => { kick(0); refreshList(true); };
  document.getElementById('push-later')?.addEventListener('click', async () => {
    await kv.set('pushDismissed', true);
    document.getElementById('push-ask')?.remove();
  });
  document.getElementById('push-on')?.addEventListener('click', turnOnPush);
}

async function turnOnPush() {
  try {
    const res = await enablePush();
    if (res === 'granted') toast(t('pushEnabled'));
    else if (res === 'denied') toast(t('pushDenied'), true, 6000);
  } catch {
    toast(t('loadFailed'), true);
  }
  document.getElementById('push-ask')?.remove();
}

// ---------------------------------------------------------------- detail zakázky

async function loadTicket(id) {
  const cached = await kv.get('ticket:' + id);
  try {
    const res = await api.ticket(id);
    await kv.set('ticket:' + id, res.data);
    await clearOverride(id, res.data.status);
    return { ticket: res.data, fresh: true };
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) { onAuthExpired(); throw e; }
    if (cached) return { ticket: cached, fresh: false };
    // ze seznamu aspoň základní údaje
    const list = await kv.get('tickets');
    const basic = list?.items.find((x) => String(x.id) === String(id));
    if (basic) return { ticket: { ...basic, visits: [], history: [], attachments: [] }, fresh: false };
    throw e;
  }
}

async function setOverride(id, status) {
  const o = (await kv.get('statusOverrides')) || {};
  o[id] = status;
  await kv.set('statusOverrides', o);
}
async function clearOverride(id, serverStatus) {
  const o = (await kv.get('statusOverrides')) || {};
  if (o[id] && (o[id] === serverStatus || !(await pendingFor(id)).length)) {
    delete o[id];
    await kv.set('statusOverrides', o);
  }
}

async function renderDetail(id) {
  setTop('…', true);
  $app.innerHTML = '<div class="spinner"></div>';

  let ticket, fresh;
  try {
    ({ ticket, fresh } = await loadTicket(id));
  } catch (e) {
    $app.innerHTML = `<div class="empty">${esc(e instanceof NetworkError ? t('offline') : t('loadFailed'))}</div>`;
    return;
  }
  if (parseRoute().id !== String(id)) return;

  const overrides = (await kv.get('statusOverrides')) || {};
  const status = overrides[id] || ticket.status;
  const visit = await resolveVisit(ticket);
  const loc = ticket.location;
  setTop(ticket.number || '#' + ticket.id, true);

  const navUrl = loc
    ? (loc.lat && loc.lng
      ? `https://www.google.com/maps/dir/?api=1&destination=${loc.lat},${loc.lng}`
      : `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent([loc.street, loc.zip, loc.city, loc.country].filter(Boolean).join(', '))}`)
    : null;
  const phone = ticket.reporter?.phone;

  let visitBtn = '';
  let banner = '';
  if (visit.mode === 'readonly') {
    banner = `<div class="banner ok">${esc(t('submittedInfo'))}</div>`;
    visitBtn = `<a class="btn" href="#/t/${id}/visit">${esc(t('viewVisit'))}</a>`;
  } else if (['closed', 'cancelled'].includes(status)) {
    visitBtn = '';
  } else {
    if (visit.returnNote) banner = `<div class="banner warn">${esc(t('returnedInfo'))}<br><strong>${esc(visit.returnNote)}</strong></div>`;
    visitBtn = `<a class="btn primary" href="#/t/${id}/visit">${esc(visit.started ? t('continueVisit') : t('startVisit'))}</a>`;
  }

  const canToggleWait = visit.mode !== 'readonly' && ['assigned', 'in_progress', 'waiting'].includes(status);
  const history = (ticket.history || []).slice(-8).reverse().map((h) =>
    `<div>${esc(fmtDate(h.at))} · ${esc(t('status.' + h.to))}${h.note ? ' – ' + esc(h.note) : ''}</div>`).join('');

  $app.innerHTML = `
    ${!fresh ? `<div class="banner warn">${esc(t('offlineCached'))}</div>` : ''}
    ${banner}
    <div class="card">
      <div class="ticket-head"><span class="ticket-num">${esc(ticket.project?.name || '')} · ${esc(ticket.service_type)}</span>${chip(status)}</div>
      <div class="ticket-title" style="font-size:18px">${esc(ticket.title)}</div>
      ${ticket.description ? `<div class="desc" style="margin-top:6px">${esc(ticket.description)}</div>` : ''}
    </div>

    ${loc ? `
    <div class="card">
      <dl class="kv">
        <dt>${esc(t('location'))}</dt><dd><strong>${esc(loc.code)}</strong> · ${esc(loc.name)}</dd>
        <dt>${esc(t('address'))}</dt><dd>${esc([loc.street, [loc.zip, loc.city].filter(Boolean).join(' '), loc.country].filter(Boolean).join(', '))}</dd>
        ${ticket.due_at ? `<dt>${esc(t('due'))}</dt><dd>${esc(fmtDate(ticket.due_at))}</dd>` : ''}
        <dt>${esc(t('priority'))}</dt><dd>${esc(t('prio.' + ticket.priority))}</dd>
        ${ticket.reporter?.name ? `<dt>${esc(t('reporter'))}</dt><dd>${esc(ticket.reporter.name)}</dd>` : ''}
      </dl>
      <div class="row" style="margin-top:12px">
        ${navUrl ? `<a class="btn" href="${esc(navUrl)}" target="_blank" rel="noopener">🧭 ${esc(t('navigate'))}</a>` : ''}
        ${phone ? `<a class="btn" href="tel:${esc(phone)}">📞 ${esc(t('call'))}</a>` : ''}
      </div>
    </div>` : ''}

    <div class="actions">
      ${visitBtn}
      ${canToggleWait ? (status === 'waiting'
        ? `<button class="btn" id="btn-status" data-to="in_progress">${esc(t('setInProgress'))}</button>`
        : `<button class="btn" id="btn-status" data-to="waiting">${esc(t('setWaiting'))}</button>`) : ''}
    </div>

    ${history ? `<div class="section-title">${esc(t('history'))}</div><div class="card history">${history}</div>` : ''}`;

  const sb = document.getElementById('btn-status');
  if (sb) {
    sb.onclick = async () => {
      const to = sb.dataset.to;
      let note = null;
      if (to === 'waiting') {
        note = await promptSheet(t('waitingNote'));
        if (note === null || note === undefined) return;
      }
      await enqueue({ type: 'status', key: 'status:' + id, ticketId: Number(id), payload: { status: to, note } });
      await setOverride(id, to);
      renderDetail(id);
    };
  }
}

/**
 * Najde návštěvu, se kterou technik pracuje:
 * rozpracovaná v telefonu → vrácená/rozpracovaná na serveru → nová.
 * Když pro návštěvu nečeká nic ve frontě, platí stav ze serveru.
 */
async function resolveVisit(ticket) {
  const id = ticket.id;
  const draftKey = 'draft:' + id;
  let draft = await kv.get(draftKey);
  const mine = (ticket.visits || []).filter((v) => v.technician?.id === state.me.id);
  const queued = await pendingFor(id);

  if (draft) {
    const server = mine.find((v) => v.uuid === draft.uuid);
    const hasQueue = queued.some((q) => q.key === draft.uuid || q.visitUuid === draft.uuid);
    if (server && !hasQueue) {
      if (server.status === 'submitted' || server.status === 'approved') {
        return { mode: 'readonly', draft: fromServer(server, id), started: true };
      }
      draft = { ...fromServer(server, id), returnNote: server.return_note };
      await kv.set(draftKey, draft);
    }
    if (draft.submittedLocally) return { mode: 'readonly', draft, started: true };
    return { mode: 'edit', draft, started: true, returnNote: draft.returnNote };
  }

  const editable = [...mine].reverse().find((v) => v.status === 'draft' || v.status === 'returned');
  if (editable) {
    draft = { ...fromServer(editable, id), returnNote: editable.return_note };
    await kv.set(draftKey, draft);
    return { mode: 'edit', draft, started: true, returnNote: editable.return_note };
  }

  const done = [...mine].reverse().find((v) => v.status === 'submitted' || v.status === 'approved');
  if (done && ['completed', 'closed'].includes(ticket.status)) {
    return { mode: 'readonly', draft: fromServer(done, id), started: true };
  }

  return { mode: 'edit', draft: newDraft(id), started: false };
}

function newDraft(ticketId) {
  return {
    uuid: uuid(), ticketId: Number(ticketId), arrived_at: null, departed_at: null, km: null,
    work_description: '', repair_description: '', test_result: null, test_note: '', instruction_ref: '',
    parts: [], serverPhotos: [],
  };
}

function fromServer(v, ticketId) {
  return {
    uuid: v.uuid, ticketId: Number(ticketId), arrived_at: v.arrived_at, departed_at: v.departed_at, km: v.km,
    work_description: v.work_description || '', repair_description: v.repair_description || '',
    test_result: v.test_result, test_note: v.test_note || '', instruction_ref: v.instruction_ref || '',
    parts: (v.parts || []).map((p) => ({ ...p })),
    serverPhotos: (v.attachments || []).filter((a) => a.kind === 'photo').map((a) => a.uuid),
    status: v.status,
  };
}

// ---------------------------------------------------------------- návštěva

async function renderVisit(id) {
  setTop(t('visit'), true);
  $app.innerHTML = '<div class="spinner"></div>';

  let ticket;
  try {
    ({ ticket } = await loadTicket(id));
  } catch {
    $app.innerHTML = `<div class="empty">${esc(t('loadFailed'))}</div>`;
    return;
  }
  if (parseRoute().id !== String(id)) return;

  const resolved = await resolveVisit(ticket);
  const d = resolved.draft;
  const readonly = resolved.mode === 'readonly';
  setTop((ticket.number || '#' + id) + ' · ' + t('visit'), true);

  const draftKey = 'draft:' + id;
  let { cached: catalog, refresh: catalogRefresh } = await loadCatalog();
  catalogRefresh.then((c) => { catalog = c; });
  const catById = (pid) => catalog.parts.find((x) => x.id === pid);

  const persist = async () => {
    if (readonly) return;
    await kv.set(draftKey, d);
  };

  const pushToServer = async () => {
    if (readonly) return;
    if (d.arrived_at && d.departed_at && new Date(d.departed_at) < new Date(d.arrived_at)) return;
    await persist();
    await enqueue({ type: 'visit', key: d.uuid, ticketId: Number(id), payload: visitPayload(d) });
  };
  const save = debounce(pushToServer, 2000);
  state.leaveHandlers.push(() => save.flush());

  const minutes = () => {
    if (!d.arrived_at || !d.departed_at) return '—';
    const m = Math.round((new Date(d.departed_at) - new Date(d.arrived_at)) / 60000);
    return m >= 0 ? m : '⚠';
  };

  function partInfo(p) {
    const c = p.part_id ? catById(p.part_id) : null;
    if (!c) return '';
    const qty = catalog.stock[c.id] ?? 0;
    return `📦 ${esc(c.part_number)} · ${esc(t('inStock'))}: <strong style="${qty <= 0 ? 'color:var(--bad)' : ''}">${qty}</strong> ${esc(c.unit || '')}${c.serialized ? ` · <strong>${esc(t('snRequired'))}</strong>` : ''}`;
  }

  const draw = async () => {
    const queued = await pendingFor(id);
    const localPhotos = queued.filter((q) => q.type === 'photo' && q.visitUuid === d.uuid);
    const dis = readonly ? 'disabled' : '';

    $app.innerHTML = `
      <div data-editing>
      ${readonly ? `<div class="banner ok">${esc(t('readonly'))}</div>` : ''}
      ${resolved.returnNote && !readonly ? `<div class="banner warn">${esc(t('returnedInfo'))}<br><strong>${esc(resolved.returnNote)}</strong></div>` : ''}

      <div class="card">
        <div class="time-row">
          <label class="field" style="margin:0"><span class="req">${esc(t('arrived'))}</span>
            <input type="datetime-local" data-f="arrived_at" value="${esc(toLocalInput(d.arrived_at))}" ${dis}></label>
          ${readonly ? '' : `<button class="btn small" data-now="arrived_at">${esc(t('now'))}</button>`}
        </div>
        <div class="time-row" style="margin-top:10px">
          <label class="field" style="margin:0"><span>${esc(t('departed'))}</span>
            <input type="datetime-local" data-f="departed_at" value="${esc(toLocalInput(d.departed_at))}" ${dis}></label>
          ${readonly ? '' : `<button class="btn small" data-now="departed_at">${esc(t('now'))}</button>`}
        </div>
        <div class="row" style="margin-top:10px">
          <label class="field" style="margin:0"><span class="req">${esc(t('km'))}</span>
            <input type="number" inputmode="decimal" min="0" step="0.1" data-f="km" value="${d.km ?? ''}" ${dis}></label>
          <label class="field" style="margin:0"><span>${esc(t('minutes'))}</span>
            <input type="text" value="${minutes()}" disabled id="minutes"></label>
        </div>
      </div>

      <div class="card">
        <label class="field"><span class="req">${esc(t('work'))}</span>
          <textarea data-f="work_description" ${dis}>${esc(d.work_description)}</textarea></label>
        <label class="field"><span>${esc(t('repair'))}</span>
          <textarea data-f="repair_description" ${dis}>${esc(d.repair_description)}</textarea></label>
        <label class="field" style="margin-bottom:0"><span>${esc(t('instruction'))}</span>
          <input type="text" data-f="instruction_ref" value="${esc(d.instruction_ref)}" ${dis}></label>
      </div>

      <div class="card">
        <div class="field"><span class="req" style="display:block;font-size:14px;color:var(--muted);font-weight:600;margin-bottom:4px">${esc(t('test'))}</span>
          <div class="seg">
            ${['ok', 'failed', 'na'].map((v) => `<button type="button" data-v="${v}" class="${d.test_result === v ? 'on' : ''}" ${dis}>${esc(t(v === 'ok' ? 'testOk' : v === 'failed' ? 'testFailed' : 'testNa'))}</button>`).join('')}
          </div>
        </div>
        <label class="field" style="margin:10px 0 0"><span>${esc(t('testNote'))}</span>
          <input type="text" data-f="test_note" value="${esc(d.test_note)}" ${dis}></label>
      </div>

      <div class="section-title">${esc(t('parts'))}</div>
      <div id="parts">
        ${d.parts.map((p, i) => `
          <div class="part" data-i="${i}">
            <div class="part-head"><span>${esc(t('part'))} ${i + 1}</span>${readonly ? '' : `<button class="btn small danger" data-rm="${i}">${esc(t('remove'))}</button>`}</div>
            <label class="field"><span class="req">${esc(t('partName'))}</span><input type="text" data-p="name" list="catalog-list" placeholder="${esc(t('fromCatalog'))}" value="${esc(p.part_id && catById(p.part_id) ? catLabel(catById(p.part_id)) : p.name)}" ${dis}></label>
            <div class="small muted" data-info style="margin:-6px 0 8px">${partInfo(p)}</div>
            <div class="part-grid">
              <label class="field"><span>${esc(t('partNumber'))}</span><input type="text" data-p="part_number" value="${esc(p.part_number || '')}" ${dis}></label>
              <label class="field"><span>${esc(t('qty'))}</span><input type="number" inputmode="decimal" min="0" step="0.01" data-p="quantity" value="${p.quantity ?? 1}" ${dis}></label>
              <label class="field"><span>${esc(t('snRemoved'))}</span><input type="text" data-p="serial_removed" value="${esc(p.serial_removed || '')}" ${dis}></label>
              <label class="field"><span>${esc(t('snInstalled'))}</span><input type="text" data-p="serial_installed" value="${esc(p.serial_installed || '')}" ${dis}></label>
            </div>
          </div>`).join('')}
      </div>
      ${readonly ? '' : `<button class="btn" id="add-part" style="width:100%">${esc(t('addPart'))}</button>
      <datalist id="catalog-list">${catalog.parts.map((c) => `<option value="${esc(catLabel(c))}"></option>`).join('')}</datalist>`}

      <div class="section-title">${esc(t('photos'))}</div>
      <div class="photos" id="photos">
        ${(d.serverPhotos || []).map((u) => `<div class="photo" data-server="${esc(u)}"><img alt=""></div>`).join('')}
        ${localPhotos.map((q) => `<div class="photo" data-local="${q.id}"><img alt=""><span class="badge">⇅ ${esc(t('waitingUpload'))}</span></div>`).join('')}
        ${readonly ? '' : `<label class="photo photo-add">${esc(t('addPhoto'))}<input type="file" accept="image/*" capture="environment" multiple hidden id="photo-in"></label>`}
      </div>

      ${readonly ? '' : `
      <div class="sticky-actions">
        <div class="row">
          <button class="btn" id="btn-save">${esc(t('save'))}</button>
          <button class="btn success" id="btn-submit">${esc(t('submit'))}</button>
        </div>
      </div>`}
      </div>`;

    // náhledy fotek
    for (const el of $app.querySelectorAll('[data-local]')) {
      const q = localPhotos.find((x) => String(x.id) === el.dataset.local);
      if (q) el.querySelector('img').src = URL.createObjectURL(q.blob);
    }
    for (const el of $app.querySelectorAll('[data-server]')) loadServerImage(el.dataset.server, el.querySelector('img'));

    if (readonly) return;

    // pole návštěvy
    for (const el of $app.querySelectorAll('[data-f]')) {
      el.addEventListener('input', () => {
        const f = el.dataset.f;
        if (f === 'arrived_at' || f === 'departed_at') d[f] = fromLocalInput(el.value);
        else if (f === 'km') d.km = el.value === '' ? null : Number(el.value);
        else d[f] = el.value;
        $app.querySelector('#minutes').value = minutes();
        persist();
        save();
      });
    }
    for (const b of $app.querySelectorAll('[data-now]')) {
      b.onclick = () => {
        d[b.dataset.now] = new Date().toISOString();
        $app.querySelector(`[data-f=${b.dataset.now}]`).value = toLocalInput(d[b.dataset.now]);
        $app.querySelector('#minutes').value = minutes();
        save.flush();
      };
    }
    for (const b of $app.querySelectorAll('.seg button')) {
      b.onclick = () => {
        d.test_result = b.dataset.v;
        $app.querySelectorAll('.seg button').forEach((x) => x.classList.toggle('on', x === b));
        save.flush();
      };
    }

    // díly
    for (const box of $app.querySelectorAll('.part')) {
      const i = Number(box.dataset.i);
      for (const el of box.querySelectorAll('[data-p]')) {
        el.addEventListener('input', () => {
          const k = el.dataset.p;
          if (k === 'name') {
            const hit = catalog.parts.find((c) => catLabel(c) === el.value);
            if (hit) {
              d.parts[i].part_id = hit.id;
              d.parts[i].name = hit.name;
              d.parts[i].part_number = hit.part_number;
              const pn = box.querySelector('[data-p=part_number]');
              if (pn) pn.value = hit.part_number;
            } else {
              d.parts[i].part_id = null;
              d.parts[i].name = el.value;
            }
            box.querySelector('[data-info]').innerHTML = partInfo(d.parts[i]);
          } else {
            d.parts[i][k] = k === 'quantity' ? (el.value === '' ? 1 : Number(el.value)) : el.value;
          }
          persist();
          save();
        });
      }
    }
    for (const b of $app.querySelectorAll('[data-rm]')) {
      b.onclick = async () => {
        d.parts.splice(Number(b.dataset.rm), 1);
        await save.flush();
        draw();
      };
    }
    $app.querySelector('#add-part').onclick = async () => {
      d.parts.push({ uuid: uuid(), part_id: null, name: '', part_number: '', quantity: 1, serial_removed: '', serial_installed: '' });
      await persist();
      await draw();
      const last = $app.querySelectorAll('.part');
      last[last.length - 1]?.querySelector('[data-p=name]')?.focus();
    };

    // fotky
    $app.querySelector('#photo-in').onchange = async (e) => {
      const files = [...e.target.files];
      if (!files.length) return;
      // návštěva musí být na serveru dřív než fotka → nejdřív uložit
      await pushToServer();
      for (const f of files) {
        try {
          const blob = await shrink(f);
          const pu = uuid();
          await enqueue({ type: 'photo', key: pu, ticketId: Number(id), visitUuid: d.uuid, blob, name: pu + '.jpg' });
        } catch {
          toast(t('loadFailed'), true);
        }
      }
      draw();
    };

    $app.querySelector('#btn-save').onclick = async () => {
      await save.flush();
      toast(t('saved'));
    };

    $app.querySelector('#btn-submit').onclick = async () => {
      const missing = [];
      if (!d.arrived_at) missing.push(t('arrived'));
      if (d.km === null || d.km === undefined || Number.isNaN(d.km)) missing.push(t('km'));
      if (!d.work_description.trim()) missing.push(t('work'));
      if (!d.test_result) missing.push(t('test'));
      if (d.parts.some((p) => !p.name.trim())) missing.push(t('partName'));
      if (d.arrived_at && d.departed_at && new Date(d.departed_at) < new Date(d.arrived_at)) missing.push(t('departed') + ' < ' + t('arrived'));
      if (missing.length) {
        toast(t('missing') + ' ' + missing.join(', '), true, 5000);
        return;
      }
      const noSn = d.parts.filter((p) => p.part_id && catById(p.part_id)?.serialized && (!p.serial_installed || !p.serial_removed));
      const warn = noSn.length ? `⚠ ${t('snMissingWarn')} ${noSn.map((p) => p.name).join(', ')}\n\n` : '';
      if (!(await confirmSheet(warn + t('confirmSubmit')))) return;

      await save.flush();
      await enqueue({ type: 'submit', key: d.uuid, ticketId: Number(id) });
      d.submittedLocally = true;
      await kv.set(draftKey, d);
      await setOverride(id, 'completed');
      state.leaveHandlers.length = 0;
      history.back();
    };
  };

  await draw();
}

/** Katalog dílů a stav mého skladu – drží se v telefonu, aby šel výběr i offline */
async function loadCatalog() {
  const cached = (await kv.get('catalog')) || { parts: [], stock: {} };
  const refresh = (async () => {
    try {
      const [parts, stock] = await Promise.all([api.catalog(), api.myStock()]);
      const map = {};
      for (const r of stock.data) map[r.part.id] = r.qty;
      const fresh = { parts: parts.data.map((p) => ({ id: p.id, part_number: p.part_number, name: p.name, unit: p.unit, serialized: p.serialized })), stock: map };
      await kv.set('catalog', fresh);
      return fresh;
    } catch {
      return cached;
    }
  })();
  return cached.parts.length ? { cached, refresh } : { cached: await refresh, refresh };
}

const catLabel = (p) => `${p.part_number} · ${p.name}`;

function visitPayload(d) {
  return {
    arrived_at: d.arrived_at,
    departed_at: d.departed_at,
    km: d.km,
    work_description: d.work_description || null,
    repair_description: d.repair_description || null,
    test_result: d.test_result,
    test_note: d.test_note || null,
    instruction_ref: d.instruction_ref || null,
    parts: d.parts.filter((p) => p.name && p.name.trim()).map((p) => ({
      uuid: p.uuid, part_id: p.part_id || null, name: p.name.trim(), part_number: p.part_number || null, quantity: p.quantity ?? 1,
      serial_removed: p.serial_removed || null, serial_installed: p.serial_installed || null,
    })),
    client_updated_at: new Date().toISOString(),
  };
}

async function loadServerImage(attUuid, img) {
  if (imageUrls.has(attUuid)) { img.src = imageUrls.get(attUuid); return; }
  try {
    const res = await api.download(attUuid);
    const url = URL.createObjectURL(await res.blob());
    imageUrls.set(attUuid, url);
    img.src = url;
  } catch { /* offline – náhled se ukáže později */ }
}

// ---------------------------------------------------------------- menu a synchronizace

function openMenu() {
  sheet(`
    <h3>${esc(state.me?.name || t('menu'))}</h3>
    <button class="menu-item" data-a="sync">⇅ ${esc(t('sync'))}</button>
    ${pushSupported() ? (pushPermission() === 'granted'
    ? `<button class="menu-item" data-a="pushtest">🔔 ${esc(t('pushOn'))} · ${esc(t('pushTest'))}</button>`
    : `<button class="menu-item" data-a="push">🔕 ${esc(t('pushOff'))} · ${esc(t('pushEnable'))}</button>`)
    : (needsInstallForPush() ? `<div class="menu-item muted small">🔕 ${esc(t('pushIos'))}</div>` : '')}
    <button class="menu-item" data-a="lang">🌐 ${esc(t('language'))}: ${getLang() === 'cs' ? 'Čeština → Polski' : 'Polski → Čeština'}</button>
    <button class="menu-item" data-a="logout">⎋ ${esc(t('logout'))}</button>
    <p class="muted small">${esc(t('version'))} ${APP_VERSION}</p>
  `, (dlg, close) => {
    dlg.querySelectorAll('[data-a]').forEach((b) => { b.onclick = () => close(b.dataset.a); });
  }).then(async (a) => {
    if (a === 'sync') openSync();
    if (a === 'push') turnOnPush();
    if (a === 'pushtest') {
      try { await api.pushTest(); toast(t('pushTestSent')); } catch { toast(t('offline'), true); }
    }
    if (a === 'lang') {
      setLang(getLang() === 'cs' ? 'pl' : 'cs');
      await kv.set('lang', getLang());
      updateOnline();
      route();
    }
    if (a === 'logout') logout();
  });
}

async function openSync() {
  const s = await stats();
  const rows = s.items.sort((a, b) => a.id - b.id).map((i) => `
    <div class="card" style="margin-bottom:8px">
      <div class="ticket-head"><strong>${esc(t('types.' + i.type))}</strong><span class="muted small">${esc(fmtDate(i.created))}</span></div>
      <div class="small muted">#${esc(i.ticketId)}</div>
      ${i.error ? `<div class="banner err small" style="margin:8px 0 0">${esc(i.error)}</div>
        <button class="btn small danger" data-discard="${i.id}" style="margin-top:8px">${esc(t('discard'))}</button>` : ''}
    </div>`).join('');

  sheet(`
    <h3>${esc(t('sync'))}</h3>
    <p>${s.pending ? esc(t('pendingN')(s.pending)) : esc(t('syncOk'))}${s.failed.length ? ' · ' + esc(t('failedN')(s.failed.length)) : ''}</p>
    <div style="max-height:50vh;overflow:auto">${rows}</div>
    <div class="row" style="margin-top:10px">
      ${s.failed.length ? `<button class="btn" data-retry>${esc(t('retry'))}</button>` : ''}
      <button class="btn primary" data-send>${esc(t('sendNow'))}</button>
    </div>
    <button class="btn" data-close style="width:100%;margin-top:8px">${esc(t('close'))}</button>
  `, (dlg, close) => {
    dlg.querySelector('[data-close]').onclick = () => close();
    dlg.querySelector('[data-send]').onclick = () => { kick(0); close(); };
    const r = dlg.querySelector('[data-retry]');
    if (r) r.onclick = () => { retryFailed(); close(); };
    dlg.querySelectorAll('[data-discard]').forEach((b) => {
      b.onclick = async () => { await discard(Number(b.dataset.discard)); close(); openSync(); };
    });
  });
}

async function logout() {
  const s = await stats();
  if (s.items.length && !(await confirmSheet(t('logoutPending')))) return;
  await disablePushForLogout();
  try { await api.logout(); } catch { /* offline – token se smaže lokálně */ }
  await outbox.clear();
  const lang = getLang();
  await kv.clear();
  await kv.set('lang', lang);
  state.me = null;
  setToken(null);
  updateSyncPill();
  location.hash = '#/login';
}

boot();

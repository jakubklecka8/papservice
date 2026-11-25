// PAP Service – zákaznický portál.
// Zákazník zadá požadavek pro svou provozovnu (i s fotkou), sleduje stav
// a po schválení vidí servisní protokol a vybrané fotky. CZ/PL.

const T = {
  cs: {
    title: 'Zákaznický portál', email: 'E-mail', password: 'Heslo', login: 'Přihlásit', loginFailed: 'Špatný e-mail nebo heslo.',
    noAccess: 'Tento účet není zákaznický. Použij dispečink nebo aplikaci technika.', logout: 'Odhlásit', lang: 'Polski',
    myRequests: 'Moje požadavky', newRequest: 'Nový požadavek', open: 'Otevřené', closed: 'Uzavřené',
    noRequests: 'Zatím žádné požadavky.', search: 'Hledat…',
    status: { new: 'Přijato', assigned: 'Přiděleno technikovi', in_progress: 'Technik pracuje', waiting: 'Čeká se (díl, přístup…)', completed: 'Hotovo, čeká na kontrolu', closed: 'Uzavřeno', cancelled: 'Zrušeno' },
    location: 'Provozovna', pickLocation: 'Vyber provozovnu', reqTitle: 'Co se stalo?', reqTitleHint: 'např. Nejde otevřít schránka č. 12',
    description: 'Podrobnosti', contactName: 'Kontaktní osoba', contactPhone: 'Telefon', photos: 'Fotky', addPhoto: '+ Přidat fotku',
    send: 'Odeslat požadavek', sending: 'Odesílám…', sent: 'Požadavek přijat', number: 'Číslo',
    created: 'Zadáno', technician: 'Technik', closedAt: 'Uzavřeno', progress: 'Průběh',
    stepReceived: 'Požadavek přijat', stepAssigned: 'Přidělen technikovi', stepWorking: 'Technik na místě / pracuje', stepDone: 'Práce dokončena', stepClosed: 'Uzavřeno, protokol k dispozici',
    protocol: 'Servisní protokol', openProtocol: 'Otevřít protokol', noProtocol: 'Protokol bude k dispozici po uzavření.',
    addMorePhotos: 'Přidat fotky', uploaded: 'Fotky nahrány', back: '← Zpět', cancel: 'Zrušit', error: 'Chyba',
    loadFailed: 'Nepodařilo se načíst data.', required: 'Vyplň provozovnu a popis problému.',
  },
  pl: {
    title: 'Portal klienta', email: 'E-mail', password: 'Hasło', login: 'Zaloguj', loginFailed: 'Błędny e-mail lub hasło.',
    noAccess: 'To konto nie jest kontem klienta. Użyj dyspozytorni lub aplikacji technika.', logout: 'Wyloguj', lang: 'Čeština',
    myRequests: 'Moje zgłoszenia', newRequest: 'Nowe zgłoszenie', open: 'Otwarte', closed: 'Zamknięte',
    noRequests: 'Brak zgłoszeń.', search: 'Szukaj…',
    status: { new: 'Przyjęte', assigned: 'Przydzielone technikowi', in_progress: 'Technik pracuje', waiting: 'Oczekuje (część, dostęp…)', completed: 'Gotowe, czeka na kontrolę', closed: 'Zamknięte', cancelled: 'Anulowane' },
    location: 'Lokalizacja', pickLocation: 'Wybierz lokalizację', reqTitle: 'Co się stało?', reqTitleHint: 'np. Nie otwiera się skrytka nr 12',
    description: 'Szczegóły', contactName: 'Osoba kontaktowa', contactPhone: 'Telefon', photos: 'Zdjęcia', addPhoto: '+ Dodaj zdjęcie',
    send: 'Wyślij zgłoszenie', sending: 'Wysyłam…', sent: 'Zgłoszenie przyjęte', number: 'Numer',
    created: 'Zgłoszono', technician: 'Technik', closedAt: 'Zamknięto', progress: 'Przebieg',
    stepReceived: 'Zgłoszenie przyjęte', stepAssigned: 'Przydzielone technikowi', stepWorking: 'Technik na miejscu / pracuje', stepDone: 'Prace zakończone', stepClosed: 'Zamknięte, protokół dostępny',
    protocol: 'Protokół serwisowy', openProtocol: 'Otwórz protokół', noProtocol: 'Protokół będzie dostępny po zamknięciu.',
    addMorePhotos: 'Dodaj zdjęcia', uploaded: 'Zdjęcia przesłane', back: '← Wstecz', cancel: 'Anuluj', error: 'Błąd',
    loadFailed: 'Nie udało się wczytać danych.', required: 'Wybierz lokalizację i opisz problem.',
  },
};

let lang = localStorage.getItem('pap_zak_lang') || 'cs';
const t = (k) => k.split('.').reduce((o, x) => (o == null ? undefined : o[x]), T[lang]) ?? k;

const state = {
  token: localStorage.getItem('pap_zak_token'),
  me: JSON.parse(localStorage.getItem('pap_zak_me') || 'null'),
  locations: null,
};

class ApiError extends Error {
  constructor(status, data) { super(data?.message || `HTTP ${status}`); this.status = status; this.data = data; }
}

async function api(method, path, body, { raw = false } = {}) {
  const headers = { Accept: 'application/json' };
  if (state.token) headers.Authorization = 'Bearer ' + state.token;
  let payload = body;
  if (body !== undefined && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
  const res = await fetch('/api/v1' + path, { method, headers, body: payload, cache: 'no-store' });
  if (res.status === 401 && path !== '/auth/login') { logout(true); throw new ApiError(401, {}); }
  if (raw && res.ok) return res;
  const text = await res.text();
  let data = null;
  if (text) { try { data = JSON.parse(text); } catch { data = { message: text.slice(0, 200) }; } }
  if (!res.ok) throw new ApiError(res.status, data);
  return data;
}

const errText = (e) => (e?.data?.errors ? Object.values(e.data.errors).flat().join(' ') : e?.message || t('error'));
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const fmt = (iso) => (iso ? new Date(iso).toLocaleString(lang === 'pl' ? 'pl-PL' : 'cs-CZ', { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—');
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) => (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)));
const addr = (l) => (l ? [l.street, [l.zip, l.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') : '');
const $root = document.getElementById('root');

let toastTimer;
function toast(msg, err = false) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast' + (err ? ' err' : '');
  el.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.hidden = true; }, err ? 6000 : 3000);
}

/** Zmenšení fotky před nahráním (max 1600 px, JPEG) */
async function shrink(file) {
  try {
    const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const s = Math.min(1, 1600 / Math.max(bmp.width, bmp.height));
    const c = document.createElement('canvas');
    c.width = Math.round(bmp.width * s);
    c.height = Math.round(bmp.height * s);
    c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
    const blob = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.82));
    return blob || file;
  } catch {
    return file;
  }
}

async function uploadPhoto(ticketId, blob) {
  const id = uuid();
  const form = new FormData();
  form.append('uuid', id);
  form.append('attachable_type', 'ticket');
  form.append('attachable_id', String(ticketId));
  form.append('kind', 'photo');
  form.append('file', blob, id + '.jpg');
  return api('POST', '/attachments', form);
}

async function openAuthed(url) {
  const w = window.open('', '_blank');
  try {
    const res = await fetch(url, { headers: { Authorization: 'Bearer ' + state.token } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    w.location = URL.createObjectURL(await res.blob());
  } catch (e) { w?.close(); toast(errText(e), true); }
}

async function loadImg(attUuid, img) {
  try {
    const res = await api('GET', `/attachments/${attUuid}/download`, undefined, { raw: true });
    img.src = URL.createObjectURL(await res.blob());
  } catch { /* nic */ }
}

async function myLocations() {
  if (!state.locations) state.locations = (await api('GET', '/locations?per_page=500')).data;
  return state.locations;
}

// ================================================================ rozvržení a router

function shell(active) {
  $root.innerHTML = `
    <header class="top"><div class="top-in">
      <div class="brand"><img src="/technik/icons/icon-192.png" alt="">PAP Service · ${esc(t('title'))}</div>
      <a class="navl ${active === 'list' ? 'on' : ''}" href="#/">${esc(t('myRequests'))}</a>
      <a class="navl ${active === 'new' ? 'on' : ''}" href="#/new">➕ ${esc(t('newRequest'))}</a>
      <span class="who">${esc(state.me.name)} <button id="b-lang">${esc(t('lang'))}</button><button id="b-out">${esc(t('logout'))}</button></span>
    </div></header>
    <main class="wrap" id="content"><div class="spinner"></div></main>`;
  document.getElementById('b-out').onclick = () => logout(false);
  document.getElementById('b-lang').onclick = () => { lang = lang === 'cs' ? 'pl' : 'cs'; localStorage.setItem('pap_zak_lang', lang); route(); };
  document.title = 'PAP Service – ' + t('title');
  return document.getElementById('content');
}

function route() {
  if (!state.token || !state.me) return renderLogin();
  const h = location.hash.replace(/^#/, '') || '/';
  const [path, qs] = h.split('?');
  const m = path.match(/^\/r\/(\d+)$/);
  if (m) return renderDetail(m[1]);
  if (path === '/new') return renderNew();
  return renderList(new URLSearchParams(qs || ''));
}

// ================================================================ přihlášení

function renderLogin(msg) {
  $root.innerHTML = `
    <div class="login-wrap"><form class="card login" id="lf">
      <img src="/technik/icons/icon-192.png" alt="">
      <h1>PAP Service<br><span class="muted" style="font-size:16px;font-weight:500">${esc(t('title'))}</span></h1>
      <label class="f"><span>${esc(t('email'))}</span><input type="email" name="email" required autocomplete="username"></label>
      <label class="f"><span>${esc(t('password'))}</span><input type="password" name="password" required autocomplete="current-password"></label>
      <div class="banner err" id="lerr" ${msg ? '' : 'hidden'}>${esc(msg || '')}</div>
      <button class="btn primary" style="width:100%">${esc(t('login'))}</button>
      <p style="text-align:center"><button type="button" class="btn sm" id="ll">${esc(t('lang'))}</button></p>
    </form></div>`;
  document.getElementById('ll').onclick = () => { lang = lang === 'cs' ? 'pl' : 'cs'; localStorage.setItem('pap_zak_lang', lang); renderLogin(); };
  const f = document.getElementById('lf');
  f.onsubmit = async (e) => {
    e.preventDefault();
    const err = document.getElementById('lerr');
    err.hidden = true;
    try {
      const res = await api('POST', '/auth/login', { email: f.email.value.trim(), password: f.password.value, device_name: 'zakaznik' });
      if (!res.user.roles.includes('zakaznik')) {
        state.token = res.token;
        await api('POST', '/auth/logout').catch(() => {});
        state.token = null;
        err.textContent = t('noAccess');
        err.hidden = false;
        return;
      }
      state.token = res.token;
      state.me = res.user;
      localStorage.setItem('pap_zak_token', res.token);
      localStorage.setItem('pap_zak_me', JSON.stringify(res.user));
      if (!localStorage.getItem('pap_zak_lang')) lang = res.user.locale || 'cs';
      location.hash = '#/';
      route();
    } catch {
      err.textContent = t('loginFailed');
      err.hidden = false;
    }
  };
}

async function logout(expired) {
  if (!expired) await api('POST', '/auth/logout').catch(() => {});
  state.token = null;
  state.me = null;
  state.locations = null;
  localStorage.removeItem('pap_zak_token');
  localStorage.removeItem('pap_zak_me');
  renderLogin();
}

// ================================================================ seznam

async function renderList(q) {
  const el = shell('list');
  const tab = q.get('tab') || 'open';
  let res;
  try {
    const p = new URLSearchParams({ per_page: '100', status: tab === 'open' ? 'new,assigned,in_progress,waiting,completed' : 'closed,cancelled' });
    res = await api('GET', '/tickets?' + p.toString());
  } catch (e) { el.innerHTML = `<div class="banner err">${esc(t('loadFailed'))}</div>`; return; }

  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('myRequests'))}</h1><a class="btn primary" href="#/new">➕ ${esc(t('newRequest'))}</a></div>
    <div class="tabs">
      <button class="tab ${tab === 'open' ? 'on' : ''}" data-tab="open">${esc(t('open'))}</button>
      <button class="tab ${tab === 'closed' ? 'on' : ''}" data-tab="closed">${esc(t('closed'))}</button>
    </div>
    <div class="req-list">
      ${res.data.map((r) => `
        <a class="card" href="#/r/${r.id}">
          <div class="req-head"><span class="muted small"><strong>${esc(r.number)}</strong> · ${esc(fmt(r.created_at))}</span><span class="chip ${esc(r.status)}">${esc(t('status.' + r.status))}</span></div>
          <div><strong>${esc(r.title)}</strong></div>
          <div class="muted small">${r.location ? esc(r.location.code + ' · ' + r.location.name) : ''}${r.technician ? ' · ' + esc(t('technician')) + ': ' + esc(r.technician.name) : ''}</div>
        </a>`).join('') || `<div class="card empty">${esc(t('noRequests'))}</div>`}
    </div>`;
  el.querySelectorAll('[data-tab]').forEach((b) => { b.onclick = () => { location.hash = '#/?tab=' + b.dataset.tab; }; });
}

// ================================================================ nový požadavek

async function renderNew() {
  const el = shell('new');
  let locs;
  try { locs = await myLocations(); } catch { el.innerHTML = `<div class="banner err">${esc(t('loadFailed'))}</div>`; return; }

  const photos = [];
  const formUuid = uuid();
  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('newRequest'))}</h1></div>
    <form class="card" id="nf" style="max-width:720px">
      <label class="f"><span class="req">${esc(t('location'))}</span>
        <select name="location_id" required>
          ${locs.length > 1 ? `<option value="">${esc(t('pickLocation'))}</option>` : ''}
          ${locs.map((l) => `<option value="${l.id}">${esc(l.code)} · ${esc(l.name)}${l.city ? ' – ' + esc(l.city) : ''}</option>`).join('')}
        </select></label>
      <label class="f"><span class="req">${esc(t('reqTitle'))}</span><input type="text" name="title" required maxlength="255" placeholder="${esc(t('reqTitleHint'))}"></label>
      <label class="f"><span>${esc(t('description'))}</span><textarea name="description"></textarea></label>
      <div class="form-grid">
        <label class="f"><span>${esc(t('contactName'))}</span><input type="text" name="reporter_name" value="${esc(state.me.name)}"></label>
        <label class="f"><span>${esc(t('contactPhone'))}</span><input type="tel" name="reporter_phone"></label>
      </div>
      <div class="f"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px">${esc(t('photos'))}</span>
        <div class="photo-pick" id="pp"></div></div>
      <div class="banner err" id="nerr" hidden></div>
      <button class="btn primary" type="submit" style="min-width:200px">${esc(t('send'))}</button>
    </form>`;

  const pp = el.querySelector('#pp');
  const drawPhotos = () => {
    pp.innerHTML = photos.map((b, i) => `<div class="photo"><img src="${URL.createObjectURL(b)}" alt=""><label data-rm="${i}">✕</label></div>`).join('')
      + `<label class="photo-add">${esc(t('addPhoto'))}<input type="file" accept="image/*" multiple hidden id="pin"></label>`;
    pp.querySelectorAll('[data-rm]').forEach((x) => { x.onclick = () => { photos.splice(Number(x.dataset.rm), 1); drawPhotos(); }; });
    pp.querySelector('#pin').onchange = async (e) => {
      for (const f of e.target.files) photos.push(await shrink(f));
      drawPhotos();
    };
  };
  drawPhotos();

  const f = el.querySelector('#nf');
  f.onsubmit = async (e) => {
    e.preventDefault();
    const err = el.querySelector('#nerr');
    err.hidden = true;
    if (!f.location_id.value || !f.title.value.trim()) { err.textContent = t('required'); err.hidden = false; return; }
    const btn = f.querySelector('[type=submit]');
    btn.disabled = true;
    btn.textContent = t('sending');
    try {
      const res = await api('POST', '/tickets', {
        uuid: formUuid,
        location_id: Number(f.location_id.value),
        title: f.title.value.trim(),
        description: f.description.value.trim() || null,
        reporter_name: f.reporter_name.value.trim() || null,
        reporter_phone: f.reporter_phone.value.trim() || null,
      });
      for (const b of photos) await uploadPhoto(res.data.id, b);
      toast(`${t('sent')}: ${res.data.number}`);
      location.hash = '#/r/' + res.data.id;
    } catch (ex) {
      err.textContent = errText(ex);
      err.hidden = false;
      btn.disabled = false;
      btn.textContent = t('send');
    }
  };
}

// ================================================================ detail

async function renderDetail(id) {
  const el = shell('list');
  let r;
  try { r = (await api('GET', '/tickets/' + id)).data; } catch { el.innerHTML = `<div class="banner err">${esc(t('loadFailed'))}</div>`; return; }

  const order = ['new', 'assigned', 'in_progress', 'completed', 'closed'];
  const stage = r.status === 'waiting' ? 2 : order.indexOf(r.status);
  const steps = [
    [t('stepReceived'), r.created_at],
    [t('stepAssigned') + (r.technician ? ` – ${r.technician.name}` : ''), r.assigned_at],
    [r.status === 'waiting' ? t('status.waiting') : t('stepWorking'), null],
    [t('stepDone'), null],
    [t('stepClosed'), r.closed_at],
  ];
  const protocols = (r.protocols || []).filter((p) => p.status === 'approved');
  const photos = (r.attachments || []).filter((a) => a.kind === 'photo');
  const open = !['closed', 'cancelled'].includes(r.status);

  el.innerHTML = `
    <p><a href="#/">${esc(t('back'))}</a></p>
    <div class="page-head"><h1>${esc(r.title)}</h1><span class="chip ${esc(r.status)}">${esc(t('status.' + r.status))}</span></div>
    <div class="grid-2">
      <div>
        <div class="card">
          <dl class="kv">
            <dt>${esc(t('number'))}</dt><dd><strong>${esc(r.number)}</strong></dd>
            <dt>${esc(t('location'))}</dt><dd>${r.location ? esc(r.location.code + ' · ' + r.location.name) + '<br><span class="muted small">' + esc(addr(r.location)) + '</span>' : '—'}</dd>
            <dt>${esc(t('created'))}</dt><dd>${esc(fmt(r.created_at))}</dd>
            ${r.technician ? `<dt>${esc(t('technician'))}</dt><dd>${esc(r.technician.name)}</dd>` : ''}
            ${r.closed_at ? `<dt>${esc(t('closedAt'))}</dt><dd>${esc(fmt(r.closed_at))}</dd>` : ''}
          </dl>
          ${r.description ? `<div class="desc" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line)">${esc(r.description)}</div>` : ''}
        </div>
        <div class="card">
          <h2>${esc(t('photos'))}</h2>
          <div class="photos">${photos.map((a) => `<div class="photo"><img data-att="${esc(a.uuid)}" alt=""></div>`).join('')}</div>
          ${open ? `<label class="btn" style="margin-top:10px">📷 ${esc(t('addMorePhotos'))}<input type="file" accept="image/*" multiple hidden id="more"></label>` : ''}
        </div>
      </div>
      <div>
        ${r.status === 'cancelled' ? '' : `<div class="card">
          <h2>${esc(t('progress'))}</h2>
          <ul class="timeline">${steps.map(([label, at], i) => `<li class="${i < stage ? 'done' : i === stage ? (r.status === 'closed' ? 'done' : 'now') : ''}">
            <div>${esc(label)}</div>${at && i <= stage ? `<div class="when">${esc(fmt(at))}</div>` : ''}</li>`).join('')}</ul>
        </div>`}
        <div class="card">
          <h2>${esc(t('protocol'))}</h2>
          ${protocols.length ? protocols.map((p) => `<button class="btn primary" data-proto="${esc(p.html_url)}">📄 ${esc(t('openProtocol'))} (${esc(p.language.toUpperCase())})</button>`).join(' ') : `<p class="muted" style="margin:0">${esc(t('noProtocol'))}</p>`}
        </div>
      </div>
    </div>`;

  el.querySelectorAll('img[data-att]').forEach((img) => {
    loadImg(img.dataset.att, img);
    img.onclick = () => openAuthed(`/api/v1/attachments/${img.dataset.att}/download`);
  });
  el.querySelectorAll('[data-proto]').forEach((b) => { b.onclick = () => openAuthed(b.dataset.proto); });
  const more = el.querySelector('#more');
  if (more) {
    more.onchange = async (e) => {
      try {
        for (const f of e.target.files) await uploadPhoto(r.id, await shrink(f));
        toast(t('uploaded'));
        renderDetail(id);
      } catch (ex) { toast(errText(ex), true); }
    };
  }
}

window.addEventListener('hashchange', route);
route();

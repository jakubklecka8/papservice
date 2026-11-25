// PAP Service – portál dispečera a administrátora.
// Čisté JS bez buildu, mluví s /api/v1 stejně jako aplikace technika.

// ================================================================ texty CZ / PL

const T = {
  cs: {
    title: 'Dispečink', email: 'E-mail', password: 'Heslo', login: 'Přihlásit', loginFailed: 'Špatný e-mail nebo heslo.',
    noAccess: 'Tento účet nemá přístup do dispečinku. Technici používají aplikaci /technik/.',
    navTickets: 'Zakázky', navNew: 'Nová zakázka', navLocations: 'Provozovny', navUsers: 'Uživatelé', navTechApp: 'Aplikace technika',
    logout: 'Odhlásit', lang: 'Polski',
    tabs: { new: 'Nové', active: 'Rozpracované', approve: 'Ke schválení', closed: 'Uzavřené', all: 'Vše' },
    allProjects: 'Všechny projekty', allTechs: 'Všichni technici', search: 'Hledat číslo, název, provozovnu…',
    col: { number: 'Číslo', title: 'Požadavek', location: 'Provozovna', tech: 'Technik', status: 'Stav', prio: 'Priorita', due: 'Termín', created: 'Založeno' },
    noTickets: 'Žádné zakázky.', prev: '← Předchozí', next: 'Další →', page: 'Strana',
    status: { new: 'Nový', assigned: 'Přidělený', in_progress: 'Probíhá', waiting: 'Čeká', completed: 'Dokončený', closed: 'Uzavřený', cancelled: 'Zrušený' },
    vstatus: { draft: 'Rozpracovaná', submitted: 'Odeslaná', returned: 'Vrácená', approved: 'Schválená' },
    prio: { low: 'Nízká', normal: 'Normální', high: 'Vysoká', urgent: 'Urgentní' },
    stype: { servis: 'Servis', profilaxe: 'Profylaxe', udrzba: 'Údržba', instalace: 'Instalace' },
    test: { ok: 'V pořádku', failed: 'Nevyhověl', na: 'Neprováděl se' },
    project: 'Projekt', location: 'Provozovna', pickLocation: 'Začni psát kód, název nebo město…', change: 'Změnit',
    reqTitle: 'Název / závada', description: 'Popis', serviceType: 'Typ', priority: 'Priorita', due: 'Termín (SLA)',
    reporter: 'Nahlásil', reporterName: 'Jméno', reporterEmail: 'E-mail', reporterPhone: 'Telefon',
    assignNow: 'Rovnou přidělit technikovi', none: '— nikdo —', create: 'Založit', save: 'Uložit', cancel: 'Zrušit', close: 'Zavřít',
    created: 'Zakázka založena', saved: 'Uloženo',
    info: 'Zakázka', edit: 'Upravit', address: 'Adresa', createdAt: 'Založeno', source: 'Zdroj', closedAt: 'Uzavřeno',
    sources: { manual: 'dispečer', portal: 'zákaznický portál', email: 'e-mail', import: 'import' },
    assignment: 'Přidělení', technician: 'Technik', assign: 'Přidělit', reassign: 'Přeřadit', assigned: 'Přiděleno',
    noTechInProject: 'V projektu nejsou technici – zobrazuji všechny.',
    actions: 'Akce', setWaiting: 'Čeká', setCancelled: 'Zrušit zakázku', reopen: 'Znovu otevřít', confirmCancel: 'Opravdu zrušit zakázku?',
    note: 'Poznámka', history: 'Historie',
    visits: 'Návštěvy', noVisits: 'Technik zatím nic nezapsal.', arrived: 'Příjezd', departed: 'Odjezd', minutes: 'Na místě', km: 'Km',
    work: 'Provedené práce', repair: 'Oprava / příčina', testResult: 'Test', instruction: 'Návod', parts: 'Díly',
    partName: 'Díl', qty: 'Ks', snOut: 'S/N demont.', snIn: 'S/N mont.', photos: 'Fotky', forCustomer: 'pro zákazníka',
    returnVisit: 'Vrátit technikovi', returnNote: 'Co má technik doplnit?', protocolCs: 'Protokol CZ', protocolPl: 'Protokol PL',
    returnedNote: 'Vráceno:', protocols: 'Protokoly', preview: 'Náhled', approve: 'Schválit a uzavřít',
    confirmApprove: 'Schválit protokol? Zakázka se uzavře a zákazník protokol uvidí.', approved: 'Schváleno',
    pstatus: { draft: 'Návrh', approved: 'Schválený' },
    locations: 'Provozovny', newLocation: 'Nová provozovna', code: 'Kód', name: 'Název', street: 'Ulice', city: 'Město', zip: 'PSČ',
    country: 'Země', lat: 'Šířka (lat)', lng: 'Délka (lng)', endCustomer: 'Koncový zákazník', active: 'Aktivní', inactive: 'Neaktivní',
    users: 'Uživatelé', newUser: 'Nový uživatel', phone: 'Telefon', language: 'Jazyk', roles: 'Role', projects: 'Projekty',
    customerLocations: 'Provozovny zákazníka', newPassword: 'Nové heslo (prázdné = beze změny)', filter: 'Filtrovat…',
    role: { admin: 'Admin', ekonom: 'Ekonom', spolecnik: 'Společník', dispecer: 'Dispečer', technik: 'Technik', zakaznik: 'Zákazník' },
    readOnly: 'Máš přístup jen pro čtení.', error: 'Chyba', conflict: 'Data návštěvy se změnila – vytvoř protokol znovu.',
    loadFailed: 'Nepodařilo se načíst data.', yes: 'Ano', no: 'Ne',
  },
  pl: {
    title: 'Dyspozytornia', email: 'E-mail', password: 'Hasło', login: 'Zaloguj', loginFailed: 'Błędny e-mail lub hasło.',
    noAccess: 'To konto nie ma dostępu do dyspozytorni. Technicy używają aplikacji /technik/.',
    navTickets: 'Zlecenia', navNew: 'Nowe zlecenie', navLocations: 'Lokalizacje', navUsers: 'Użytkownicy', navTechApp: 'Aplikacja technika',
    logout: 'Wyloguj', lang: 'Čeština',
    tabs: { new: 'Nowe', active: 'W realizacji', approve: 'Do zatwierdzenia', closed: 'Zamknięte', all: 'Wszystkie' },
    allProjects: 'Wszystkie projekty', allTechs: 'Wszyscy technicy', search: 'Szukaj numeru, nazwy, lokalizacji…',
    col: { number: 'Numer', title: 'Zgłoszenie', location: 'Lokalizacja', tech: 'Technik', status: 'Status', prio: 'Priorytet', due: 'Termin', created: 'Utworzono' },
    noTickets: 'Brak zleceń.', prev: '← Poprzednia', next: 'Następna →', page: 'Strona',
    status: { new: 'Nowe', assigned: 'Przydzielone', in_progress: 'W toku', waiting: 'Oczekuje', completed: 'Zakończone', closed: 'Zamknięte', cancelled: 'Anulowane' },
    vstatus: { draft: 'Robocza', submitted: 'Wysłana', returned: 'Zwrócona', approved: 'Zatwierdzona' },
    prio: { low: 'Niski', normal: 'Normalny', high: 'Wysoki', urgent: 'Pilny' },
    stype: { servis: 'Serwis', profilaxe: 'Profilaktyka', udrzba: 'Konserwacja', instalace: 'Instalacja' },
    test: { ok: 'Poprawny', failed: 'Negatywny', na: 'Nie wykonano' },
    project: 'Projekt', location: 'Lokalizacja', pickLocation: 'Wpisz kod, nazwę lub miasto…', change: 'Zmień',
    reqTitle: 'Nazwa / usterka', description: 'Opis', serviceType: 'Typ', priority: 'Priorytet', due: 'Termin (SLA)',
    reporter: 'Zgłaszający', reporterName: 'Imię i nazwisko', reporterEmail: 'E-mail', reporterPhone: 'Telefon',
    assignNow: 'Od razu przydziel technikowi', none: '— nikt —', create: 'Utwórz', save: 'Zapisz', cancel: 'Anuluj', close: 'Zamknij',
    created: 'Zlecenie utworzone', saved: 'Zapisano',
    info: 'Zlecenie', edit: 'Edytuj', address: 'Adres', createdAt: 'Utworzono', source: 'Źródło', closedAt: 'Zamknięto',
    sources: { manual: 'dyspozytor', portal: 'portal klienta', email: 'e-mail', import: 'import' },
    assignment: 'Przydział', technician: 'Technik', assign: 'Przydziel', reassign: 'Zmień technika', assigned: 'Przydzielono',
    noTechInProject: 'W projekcie brak techników – pokazuję wszystkich.',
    actions: 'Akcje', setWaiting: 'Oczekuje', setCancelled: 'Anuluj zlecenie', reopen: 'Otwórz ponownie', confirmCancel: 'Na pewno anulować zlecenie?',
    note: 'Uwaga', history: 'Historia',
    visits: 'Wizyty', noVisits: 'Technik jeszcze nic nie zapisał.', arrived: 'Przyjazd', departed: 'Wyjazd', minutes: 'Na miejscu', km: 'Km',
    work: 'Wykonane prace', repair: 'Naprawa / przyczyna', testResult: 'Test', instruction: 'Instrukcja', parts: 'Części',
    partName: 'Część', qty: 'Szt.', snOut: 'S/N zdem.', snIn: 'S/N zam.', photos: 'Zdjęcia', forCustomer: 'dla klienta',
    returnVisit: 'Zwróć technikowi', returnNote: 'Co technik ma uzupełnić?', protocolCs: 'Protokół CZ', protocolPl: 'Protokół PL',
    returnedNote: 'Zwrócono:', protocols: 'Protokoły', preview: 'Podgląd', approve: 'Zatwierdź i zamknij',
    confirmApprove: 'Zatwierdzić protokół? Zlecenie zostanie zamknięte, a klient zobaczy protokół.', approved: 'Zatwierdzono',
    pstatus: { draft: 'Projekt', approved: 'Zatwierdzony' },
    locations: 'Lokalizacje', newLocation: 'Nowa lokalizacja', code: 'Kod', name: 'Nazwa', street: 'Ulica', city: 'Miasto', zip: 'Kod pocztowy',
    country: 'Kraj', lat: 'Szerokość (lat)', lng: 'Długość (lng)', endCustomer: 'Klient końcowy', active: 'Aktywna', inactive: 'Nieaktywna',
    users: 'Użytkownicy', newUser: 'Nowy użytkownik', phone: 'Telefon', language: 'Język', roles: 'Role', projects: 'Projekty',
    customerLocations: 'Lokalizacje klienta', newPassword: 'Nowe hasło (puste = bez zmian)', filter: 'Filtruj…',
    role: { admin: 'Admin', ekonom: 'Ekonomista', spolecnik: 'Wspólnik', dispecer: 'Dyspozytor', technik: 'Technik', zakaznik: 'Klient' },
    readOnly: 'Masz dostęp tylko do odczytu.', error: 'Błąd', conflict: 'Dane wizyty się zmieniły – utwórz protokół ponownie.',
    loadFailed: 'Nie udało się wczytać danych.', yes: 'Tak', no: 'Nie',
  },
};

let lang = localStorage.getItem('pap_portal_lang') || 'cs';
const t = (k) => k.split('.').reduce((o, x) => (o == null ? undefined : o[x]), T[lang]) ?? k;

// ================================================================ API

const state = {
  token: localStorage.getItem('pap_portal_token'),
  me: JSON.parse(localStorage.getItem('pap_portal_me') || 'null'),
  projects: null,
};

class ApiError extends Error {
  constructor(status, data) { super(data?.message || `HTTP ${status}`); this.status = status; this.data = data; }
}

async function api(method, path, body, { raw = false } = {}) {
  const headers = { Accept: 'application/json' };
  if (state.token) headers.Authorization = 'Bearer ' + state.token;
  let payload;
  if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }

  const res = await fetch('/api/v1' + path, { method, headers, body: payload, cache: 'no-store' });
  if (res.status === 401 && path !== '/auth/login') { logout(true); throw new ApiError(401, {}); }
  if (raw && res.ok) return res;
  const text = await res.text();
  let data = null;
  if (text) { try { data = JSON.parse(text); } catch { data = { message: text.slice(0, 200) }; } }
  if (!res.ok) throw new ApiError(res.status, data);
  return data;
}

function errText(e) {
  if (e?.data?.errors) return Object.values(e.data.errors).flat().join(' ');
  return e?.message || t('error');
}

// ================================================================ pomocníci

const $root = document.getElementById('root');
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const locale = () => (lang === 'pl' ? 'pl-PL' : 'cs-CZ');
const fmt = (iso, time = true) => {
  if (!iso) return '—';
  const d = new Date(iso);
  return isNaN(d) ? '—' : d.toLocaleString(locale(), time
    ? { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }
    : { day: 'numeric', month: 'numeric', year: 'numeric' });
};
const toLocalInput = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  const p = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
};
const fromLocalInput = (v) => (v ? new Date(v).toISOString() : null);
const chip = (cls, text) => `<span class="chip ${esc(cls)}">${esc(text)}</span>`;
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2));
const debounce = (fn, ms) => { let h; return (...a) => { clearTimeout(h); h = setTimeout(() => fn(...a), ms); }; };
const addrOf = (l) => (l ? [l.street, [l.zip, l.city].filter(Boolean).join(' '), l.country].filter(Boolean).join(', ') : '');

let toastTimer;
function toast(msg, err = false) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = 'toast' + (err ? ' err' : '');
  el.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.hidden = true; }, err ? 6000 : 3000);
}

/** Modální okno. bind(dlg, close) napojí ovládání; vrací Promise s výsledkem. */
function modal(html, bind) {
  const dlg = document.getElementById('modal');
  dlg.innerHTML = html;
  return new Promise((resolve) => {
    const close = (v) => { dlg.close(); resolve(v); };
    dlg.oncancel = () => resolve(undefined);
    dlg.querySelectorAll('[data-close]').forEach((b) => { b.onclick = () => close(undefined); });
    if (bind) bind(dlg, close);
    dlg.showModal();
  });
}

function confirmModal(text) {
  return modal(`<p>${esc(text)}</p><div class="modal-foot"><button class="btn" data-close>${esc(t('no'))}</button><button class="btn primary" data-yes>${esc(t('yes'))}</button></div>`,
    (d, close) => { d.querySelector('[data-yes]').onclick = () => close(true); });
}

function promptModal(title, required = false) {
  return modal(`<h3>${esc(title)}</h3><textarea data-in></textarea>
    <div class="modal-foot"><button class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary" data-ok>OK</button></div>`,
  (d, close) => {
    d.querySelector('[data-ok]').onclick = () => {
      const v = d.querySelector('[data-in]').value.trim();
      if (required && !v) { d.querySelector('[data-in]').focus(); return; }
      close(v);
    };
  });
}

/** Otevře soubor/HTML z API (s tokenem) v novém okně */
async function openAuthed(url) {
  const w = window.open('', '_blank');
  try {
    const res = await fetch(url, { headers: { Authorization: 'Bearer ' + state.token } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const blob = await res.blob();
    w.location = URL.createObjectURL(blob);
  } catch (e) {
    w?.close();
    toast(errText(e), true);
  }
}

const imgCache = new Map();
async function loadImg(uuidAtt, img) {
  if (imgCache.has(uuidAtt)) { img.src = imgCache.get(uuidAtt); return; }
  try {
    const res = await api('GET', `/attachments/${uuidAtt}/download`, undefined, { raw: true });
    const u = URL.createObjectURL(await res.blob());
    imgCache.set(uuidAtt, u);
    img.src = u;
  } catch { /* nic */ }
}

const has = (...roles) => !!state.me && roles.some((r) => state.me.roles.includes(r));
const isAdmin = () => has('admin');
const managesProject = (pid) => isAdmin() || (has('dispecer') && state.me.project_ids.map(Number).includes(Number(pid)));
const canCreate = () => isAdmin() || (has('dispecer') && state.me.project_ids.length > 0);

async function projects() {
  if (!state.projects) state.projects = (await api('GET', '/projects')).data;
  return state.projects;
}

const techCache = new Map();
async function technicians(projectId) {
  const key = String(projectId || '');
  if (!techCache.has(key)) {
    let list = (await api('GET', '/technicians' + (projectId ? `?project_id=${projectId}` : ''))).data;
    let fallback = false;
    if (projectId && !list.length) {
      list = (await api('GET', '/technicians')).data;
      fallback = true;
    }
    techCache.set(key, { list, fallback });
  }
  return techCache.get(key);
}

// ================================================================ router a rozvržení

function parseHash() {
  const [path, qs] = location.hash.replace(/^#/, '').split('?');
  return { path: path || '/', q: new URLSearchParams(qs || '') };
}

async function route() {
  const { path, q } = parseHash();
  if (!state.token || !state.me) { renderLogin(); return; }

  let m;
  if (path === '/' || path === '') return renderTickets(q);
  if (path === '/new') return renderNewTicket();
  if ((m = path.match(/^\/t\/(\d+)$/))) return renderTicket(m[1]);
  if (path === '/locations') return renderLocations(q);
  if (path === '/users' && isAdmin()) return renderUsers();
  location.hash = '#/';
}

function shell(active) {
  const nav = [
    ['tickets', '#/', '📋', t('navTickets')],
    canCreate() ? ['new', '#/new', '➕', t('navNew')] : null,
    ['locations', '#/locations', '📍', t('navLocations')],
    isAdmin() ? ['users', '#/users', '👥', t('navUsers')] : null,
  ].filter(Boolean);

  $root.innerHTML = `
    <div class="layout">
      <aside class="side">
        <div class="brand"><img src="/technik/icons/icon-192.png" alt="">PAP ${esc(t('title'))}</div>
        <nav class="nav">
          ${nav.map(([k, href, ic, label]) => `<a href="${href}" class="${k === active ? 'on' : ''}">${ic} ${esc(label)}</a>`).join('')}
          <a href="/technik/" target="_blank">📱 ${esc(t('navTechApp'))}</a>
        </nav>
        <div class="side-foot">
          <div class="who">${esc(state.me.name)}</div>
          <div>${state.me.roles.map((r) => esc(t('role.' + r))).join(', ')}</div>
          <button id="btn-lang">${esc(t('lang'))}</button> · <button id="btn-logout">${esc(t('logout'))}</button>
        </div>
      </aside>
      <main class="content" id="content"><div class="spinner"></div></main>
    </div>`;
  document.getElementById('btn-logout').onclick = () => logout(false);
  document.getElementById('btn-lang').onclick = () => {
    lang = lang === 'cs' ? 'pl' : 'cs';
    localStorage.setItem('pap_portal_lang', lang);
    route();
  };
  document.title = `PAP ${t('title')}`;
  return document.getElementById('content');
}

// ================================================================ přihlášení

function renderLogin(msg) {
  $root.innerHTML = `
    <div class="login-wrap">
      <form class="card login" id="lf">
        <img src="/technik/icons/icon-192.png" alt="">
        <h1>PAP Service – ${esc(t('title'))}</h1>
        <label class="f"><span>${esc(t('email'))}</span><input type="email" name="email" required autocomplete="username"></label>
        <label class="f"><span>${esc(t('password'))}</span><input type="password" name="password" required autocomplete="current-password"></label>
        <div class="banner err" id="lerr" ${msg ? '' : 'hidden'}>${esc(msg || '')}</div>
        <button class="btn primary" style="width:100%">${esc(t('login'))}</button>
        <p style="text-align:center"><button type="button" class="btn sm" id="ll">${esc(t('lang'))}</button></p>
      </form>
    </div>`;
  document.getElementById('ll').onclick = () => { lang = lang === 'cs' ? 'pl' : 'cs'; localStorage.setItem('pap_portal_lang', lang); renderLogin(); };
  const f = document.getElementById('lf');
  f.onsubmit = async (e) => {
    e.preventDefault();
    const err = document.getElementById('lerr');
    err.hidden = true;
    try {
      const res = await api('POST', '/auth/login', { email: f.email.value.trim(), password: f.password.value, device_name: 'portal' });
      const ok = res.user.roles.some((r) => ['admin', 'dispecer', 'spolecnik', 'ekonom'].includes(r));
      if (!ok) {
        state.token = res.token;
        await api('POST', '/auth/logout').catch(() => {});
        state.token = null;
        err.textContent = t('noAccess');
        err.hidden = false;
        return;
      }
      state.token = res.token;
      state.me = res.user;
      localStorage.setItem('pap_portal_token', res.token);
      localStorage.setItem('pap_portal_me', JSON.stringify(res.user));
      if (!localStorage.getItem('pap_portal_lang')) { lang = res.user.locale || 'cs'; }
      location.hash = '#/';
      route();
    } catch (ex) {
      err.textContent = ex.status === 422 || ex.status === 429 ? errText(ex) || t('loginFailed') : t('loginFailed');
      err.hidden = false;
    }
  };
}

async function logout(expired) {
  if (!expired) await api('POST', '/auth/logout').catch(() => {});
  state.token = null;
  state.me = null;
  state.projects = null;
  techCache.clear();
  localStorage.removeItem('pap_portal_token');
  localStorage.removeItem('pap_portal_me');
  renderLogin();
}

// ================================================================ seznam zakázek

const TAB_STATUS = { new: 'new', active: 'assigned,in_progress,waiting', approve: 'completed', closed: 'closed,cancelled', all: '' };
let listTimer;

async function renderTickets(q) {
  const el = shell('tickets');
  const tab = q.get('tab') || 'active';
  const project = q.get('project') || '';
  const tech = q.get('tech') || '';
  const search = q.get('q') || '';
  const page = Number(q.get('page') || 1);

  const setQ = (changes) => {
    const n = new URLSearchParams(q);
    Object.entries(changes).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k)));
    if (!('page' in changes)) n.delete('page');
    location.hash = '#/?' + n.toString();
  };

  let prj, techs, summary;
  try {
    [prj, techs, summary] = await Promise.all([
      projects(),
      has('admin', 'dispecer') ? technicians(project) : Promise.resolve({ list: [] }),
      api('GET', '/tickets/summary' + (project ? `?project_id=${project}` : '')),
    ]);
  } catch (e) {
    el.innerHTML = `<div class="banner err">${esc(t('loadFailed'))} ${esc(errText(e))}</div>`;
    return;
  }
  const cnt = summary.data || {};
  const count = (tabKey) => (TAB_STATUS[tabKey] ? TAB_STATUS[tabKey].split(',') : Object.keys(cnt)).reduce((s, k) => s + (Number(cnt[k]) || 0), 0);

  el.innerHTML = `
    <div class="page-head">
      <h1>${esc(t('navTickets'))}</h1>
      ${canCreate() ? `<a class="btn primary" href="#/new">➕ ${esc(t('navNew'))}</a>` : ''}
    </div>
    <div class="tabs">
      ${Object.keys(TAB_STATUS).map((k) => `<button class="tab ${k === tab ? 'on' : ''} ${k === 'approve' && count(k) ? 'alert' : ''}" data-tab="${k}">${esc(t('tabs.' + k))}<span class="n">${count(k)}</span></button>`).join('')}
    </div>
    <div class="filters">
      <select id="f-project"><option value="">${esc(t('allProjects'))}</option>${prj.map((p) => `<option value="${p.id}" ${String(p.id) === project ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>
      ${techs.list.length ? `<select id="f-tech"><option value="">${esc(t('allTechs'))}</option>${techs.list.map((u) => `<option value="${u.id}" ${String(u.id) === tech ? 'selected' : ''}>${esc(u.name)}</option>`).join('')}</select>` : ''}
      <input type="search" id="f-q" placeholder="${esc(t('search'))}" value="${esc(search)}">
    </div>
    <div id="list"><div class="spinner"></div></div>`;

  el.querySelectorAll('[data-tab]').forEach((b) => { b.onclick = () => setQ({ tab: b.dataset.tab }); });
  el.querySelector('#f-project').onchange = (e) => setQ({ project: e.target.value, tech: '' });
  el.querySelector('#f-tech')?.addEventListener('change', (e) => setQ({ tech: e.target.value }));
  el.querySelector('#f-q').addEventListener('input', debounce((e) => setQ({ q: e.target.value.trim() }), 450));
  if (search) { const i = el.querySelector('#f-q'); i.focus(); i.setSelectionRange(search.length, search.length); }

  const params = new URLSearchParams({ per_page: '50', page: String(page) });
  if (TAB_STATUS[tab]) params.set('status', TAB_STATUS[tab]);
  if (project) params.set('project_id', project);
  if (tech) params.set('technician_id', tech);
  if (search) params.set('q', search);

  let res;
  try {
    res = await api('GET', '/tickets?' + params.toString());
  } catch (e) {
    el.querySelector('#list').innerHTML = `<div class="banner err">${esc(errText(e))}</div>`;
    return;
  }

  const now = Date.now();
  const rows = res.data.map((tk) => {
    const overdue = tk.due_at && new Date(tk.due_at).getTime() < now && !['completed', 'closed', 'cancelled'].includes(tk.status);
    return `
      <tr class="prio-${esc(tk.priority)}" data-id="${tk.id}">
        <td class="num">${esc(tk.number || '#' + tk.id)}</td>
        <td><strong>${esc(tk.title)}</strong><div class="muted small">${esc(tk.project?.name || '')} · ${esc(t('stype.' + tk.service_type))}</div></td>
        <td>${tk.location ? `${esc(tk.location.code)} · ${esc(tk.location.name)}<div class="muted small">${esc(tk.location.city || '')}</div>` : '<span class="muted">—</span>'}</td>
        <td>${tk.technician ? esc(tk.technician.name) : '<span class="muted">—</span>'}</td>
        <td>${chip(tk.status, t('status.' + tk.status))}</td>
        <td>${tk.priority === 'normal' ? '<span class="muted">' + esc(t('prio.normal')) + '</span>' : esc(t('prio.' + tk.priority))}</td>
        <td class="${overdue ? 'overdue' : ''}">${esc(fmt(tk.due_at))}</td>
        <td class="muted small">${esc(fmt(tk.created_at))}</td>
      </tr>`;
  }).join('');

  const meta = res.meta || {};
  el.querySelector('#list').innerHTML = res.data.length ? `
    <div class="table-wrap"><table>
      <thead><tr>${['number', 'title', 'location', 'tech', 'status', 'prio', 'due', 'created'].map((c) => `<th>${esc(t('col.' + c))}</th>`).join('')}</tr></thead>
      <tbody>${rows}</tbody>
    </table></div>
    ${meta.last_page > 1 ? `<div class="pager">
      <button class="btn sm" data-p="${page - 1}" ${page <= 1 ? 'disabled' : ''}>${esc(t('prev'))}</button>
      <span class="muted small">${esc(t('page'))} ${meta.current_page} / ${meta.last_page}</span>
      <button class="btn sm" data-p="${page + 1}" ${page >= meta.last_page ? 'disabled' : ''}>${esc(t('next'))}</button>
    </div>` : ''}` : `<div class="card empty">${esc(t('noTickets'))}</div>`;

  el.querySelectorAll('tr[data-id]').forEach((tr) => { tr.onclick = () => { location.hash = '#/t/' + tr.dataset.id; }; });
  el.querySelectorAll('[data-p]').forEach((b) => { b.onclick = () => setQ({ page: b.dataset.p }); });

  clearTimeout(listTimer);
  const myHash = location.hash;
  listTimer = setTimeout(() => { if (location.hash === myHash && !document.getElementById('modal').open) route(); }, 60000);
}

// ================================================================ výběr provozovny

/** Vyhledávací pole provozovny v rámci projektu. onPick(location|null) */
function locationPicker(box, getProjectId, onPick, initial = null) {
  let picked = initial;
  const draw = () => {
    if (picked) {
      box.innerHTML = `<div class="picked"><span class="sp"><strong>${esc(picked.code)}</strong> · ${esc(picked.name)}<br><span class="muted small">${esc(addrOf(picked))}</span></span>
        <button type="button" class="btn sm">${esc(t('change'))}</button></div>`;
      box.querySelector('button').onclick = () => { picked = null; onPick(null); draw(); box.querySelector('input')?.focus(); };
      return;
    }
    box.innerHTML = `<div class="search-box"><input type="search" placeholder="${esc(t('pickLocation'))}"><div class="search-results" hidden></div></div>`;
    const input = box.querySelector('input');
    const results = box.querySelector('.search-results');
    const search = debounce(async () => {
      const pid = getProjectId();
      const qv = input.value.trim();
      if (!qv) { results.hidden = true; return; }
      try {
        const res = await api('GET', `/locations?q=${encodeURIComponent(qv)}${pid ? `&project_id=${pid}` : ''}`);
        results.innerHTML = res.data.length
          ? res.data.map((l, i) => `<button type="button" data-i="${i}"><strong>${esc(l.code)}</strong> · ${esc(l.name)}<br><span class="muted small">${esc(addrOf(l))}</span></button>`).join('')
          : `<div class="muted small" style="padding:10px">—</div>`;
        results.hidden = false;
        results.querySelectorAll('[data-i]').forEach((b) => {
          b.onclick = () => { picked = res.data[Number(b.dataset.i)]; onPick(picked); draw(); };
        });
      } catch { /* nic */ }
    }, 300);
    input.addEventListener('input', search);
  };
  draw();
}

// ================================================================ nová zakázka

async function renderNewTicket() {
  const el = shell('new');
  let prj;
  try { prj = (await projects()).filter((p) => managesProject(p.id)); } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  let loc = null;
  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('navNew'))}</h1></div>
    <form class="card" id="nf" style="max-width:860px">
      <div class="form-grid">
        <label class="f"><span class="req">${esc(t('project'))}</span>
          <select name="project_id" required>${prj.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('serviceType'))}</span><select name="service_type"></select></label>
        <div class="f full"><span class="req" style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:4px">${esc(t('location'))}</span><div id="loc"></div></div>
        <label class="f full"><span class="req">${esc(t('reqTitle'))}</span><input type="text" name="title" required maxlength="255"></label>
        <label class="f full"><span>${esc(t('description'))}</span><textarea name="description"></textarea></label>
        <label class="f"><span>${esc(t('priority'))}</span><select name="priority">${['normal', 'high', 'urgent', 'low'].map((p) => `<option value="${p}">${esc(t('prio.' + p))}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('due'))}</span><input type="datetime-local" name="due_at"></label>
        <label class="f"><span>${esc(t('reporterName'))}</span><input type="text" name="reporter_name"></label>
        <label class="f"><span>${esc(t('reporterPhone'))}</span><input type="tel" name="reporter_phone"></label>
        <label class="f"><span>${esc(t('reporterEmail'))}</span><input type="email" name="reporter_email"></label>
        <label class="f"><span>${esc(t('assignNow'))}</span><select name="technician_id"></select></label>
      </div>
      <div class="banner err" id="nerr" hidden></div>
      <div class="btn-row"><button class="btn primary" type="submit">${esc(t('create'))}</button><a class="btn" href="#/">${esc(t('cancel'))}</a></div>
    </form>`;

  const f = el.querySelector('#nf');
  const formUuid = uuid();
  const onProject = async () => {
    const p = prj.find((x) => String(x.id) === f.project_id.value);
    f.service_type.innerHTML = (p?.service_types || ['servis']).map((s) => `<option value="${s}">${esc(t('stype.' + s))}</option>`).join('');
    const techs = await technicians(f.project_id.value);
    f.technician_id.innerHTML = `<option value="">${esc(t('none'))}</option>` + techs.list.map((u) => `<option value="${u.id}">${esc(u.name)}</option>`).join('');
    loc = null;
    locationPicker(el.querySelector('#loc'), () => f.project_id.value, (l) => { loc = l; });
  };
  f.project_id.onchange = onProject;
  await onProject();

  f.onsubmit = async (e) => {
    e.preventDefault();
    const err = el.querySelector('#nerr');
    err.hidden = true;
    if (!loc) { err.textContent = t('location') + ' – ' + t('pickLocation'); err.hidden = false; return; }
    const btn = f.querySelector('[type=submit]');
    btn.disabled = true;
    try {
      const res = await api('POST', '/tickets', {
        uuid: formUuid,
        project_id: Number(f.project_id.value),
        location_id: loc.id,
        title: f.title.value.trim(),
        description: f.description.value.trim() || null,
        service_type: f.service_type.value,
        priority: f.priority.value,
        due_at: fromLocalInput(f.due_at.value),
        reporter_name: f.reporter_name.value.trim() || null,
        reporter_phone: f.reporter_phone.value.trim() || null,
        reporter_email: f.reporter_email.value.trim() || null,
      });
      if (f.technician_id.value) {
        await api('POST', `/tickets/${res.data.id}/assign`, { technician_id: Number(f.technician_id.value) });
      }
      toast(t('created') + ': ' + res.data.number);
      location.hash = '#/t/' + res.data.id;
    } catch (ex) {
      err.textContent = errText(ex);
      err.hidden = false;
    } finally {
      btn.disabled = false;
    }
  };
}

// ================================================================ detail zakázky

async function renderTicket(id) {
  const el = shell('tickets');
  let tk;
  try { tk = (await api('GET', '/tickets/' + id)).data; } catch (e) {
    el.innerHTML = `<div class="banner err">${esc(e.status === 403 || e.status === 404 ? t('loadFailed') : errText(e))}</div>`;
    return;
  }
  const manage = managesProject(tk.project?.id);
  const open = !['closed', 'cancelled'].includes(tk.status);
  const loc = tk.location;

  const visitsHtml = (tk.visits || []).map((v) => {
    const parts = (v.parts || []).map((p) => `<tr><td>${esc(p.name)}${p.part_number ? ` <span class="muted small">(${esc(p.part_number)})</span>` : ''}</td><td>${esc(p.quantity)}</td><td>${esc(p.serial_removed || '—')}</td><td>${esc(p.serial_installed || '—')}</td></tr>`).join('');
    const photos = (v.attachments || []).filter((a) => a.kind === 'photo');
    return `
      <div class="visit" data-visit="${esc(v.uuid)}">
        <div class="visit-head"><strong>${esc(v.technician?.name || '')}</strong>${chip(v.status, t('vstatus.' + v.status))}</div>
        ${v.status === 'returned' && v.return_note ? `<div class="banner warn small">${esc(t('returnedNote'))} ${esc(v.return_note)}</div>` : ''}
        <dl class="kv">
          <dt>${esc(t('arrived'))}</dt><dd>${esc(fmt(v.arrived_at))}</dd>
          <dt>${esc(t('departed'))}</dt><dd>${esc(fmt(v.departed_at))}</dd>
          <dt>${esc(t('minutes'))}</dt><dd>${v.minutes_on_site != null ? esc(v.minutes_on_site) + ' min' : '—'} · ${esc(t('km'))}: ${v.km != null ? esc(v.km) : '—'}</dd>
          <dt>${esc(t('work'))}</dt><dd class="desc">${esc(v.work_description || '—')}</dd>
          <dt>${esc(t('repair'))}</dt><dd class="desc">${esc(v.repair_description || '—')}</dd>
          <dt>${esc(t('testResult'))}</dt><dd>${v.test_result ? esc(t('test.' + v.test_result)) : '—'}${v.test_note ? ' – ' + esc(v.test_note) : ''}</dd>
          ${v.instruction_ref ? `<dt>${esc(t('instruction'))}</dt><dd>${esc(v.instruction_ref)}</dd>` : ''}
        </dl>
        ${parts ? `<table class="parts"><thead><tr><th>${esc(t('partName'))}</th><th>${esc(t('qty'))}</th><th>${esc(t('snOut'))}</th><th>${esc(t('snIn'))}</th></tr></thead><tbody>${parts}</tbody></table>` : ''}
        ${photos.length ? `<div class="photos">${photos.map((a) => `
          <div class="photo"><img data-att="${esc(a.uuid)}" alt="">
            <label>${manage ? `<input type="checkbox" data-vis="${esc(a.uuid)}" ${a.customer_visible ? 'checked' : ''}>` : (a.customer_visible ? '✓' : '')} ${esc(t('forCustomer'))}</label>
          </div>`).join('')}</div>` : ''}
        ${manage && v.status === 'submitted' ? `
          <div class="btn-row" style="margin-top:12px">
            <button class="btn success" data-proto="cs" data-v="${esc(v.uuid)}">📄 ${esc(t('protocolCs'))}</button>
            <button class="btn success" data-proto="pl" data-v="${esc(v.uuid)}">📄 ${esc(t('protocolPl'))}</button>
            <button class="btn danger" data-return="${esc(v.uuid)}">↩ ${esc(t('returnVisit'))}</button>
          </div>` : ''}
      </div>`;
  }).join('');

  // fotky přímo u zakázky (např. od zákazníka) – fotky z návštěv se ukazují u návštěv
  const visitAtt = new Set((tk.visits || []).flatMap((v) => (v.attachments || []).map((a) => a.uuid)));
  const ticketPhotos = (tk.attachments || []).filter((a) => a.kind === 'photo' && !visitAtt.has(a.uuid));
  const protocols = (tk.protocols || []).map((p) => `
    <div class="visit-head" style="border-bottom:1px solid var(--line);padding-bottom:8px">
      <strong>${esc(p.language.toUpperCase())}</strong>${chip(p.status === 'approved' ? 'approved' : 'draft', t('pstatus.' + p.status))}
      <span class="muted small">${esc(fmt(p.approved_at))}</span>
      <button class="btn sm" data-preview="${esc(p.html_url)}">${esc(t('preview'))}</button>
      ${manage && p.status === 'draft' ? `<button class="btn sm success" data-approve="${p.id}">✓ ${esc(t('approve'))}</button>` : ''}
    </div>`).join('');

  const history = (tk.history || []).slice().reverse().map((h) => `<div>${esc(fmt(h.at))} · <strong>${esc(t('status.' + h.to))}</strong>${h.user ? ' · ' + esc(h.user) : ''}${h.note ? `<div class="muted small">${esc(h.note)}</div>` : ''}</div>`).join('');

  el.innerHTML = `
    <div class="page-head">
      <h1>${esc(tk.number || '#' + tk.id)} · ${esc(tk.title)}</h1>
      ${chip(tk.status, t('status.' + tk.status))}
    </div>
    ${!manage ? `<div class="banner warn">${esc(t('readOnly'))}</div>` : ''}
    <div class="grid-2">
      <div>
        <div class="card">
          <h2>${esc(t('info'))}<span class="sp"></span>${manage && open ? `<button class="btn sm" id="btn-edit">✎ ${esc(t('edit'))}</button>` : ''}</h2>
          <dl class="kv">
            <dt>${esc(t('project'))}</dt><dd>${esc(tk.project?.name || '')} · ${esc(t('stype.' + tk.service_type))}</dd>
            <dt>${esc(t('location'))}</dt><dd>${loc ? `<strong>${esc(loc.code)}</strong> · ${esc(loc.name)}` : '—'}</dd>
            ${loc ? `<dt>${esc(t('address'))}</dt><dd>${esc(addrOf(loc))}${loc.lat && loc.lng ? ` · <a href="https://www.google.com/maps?q=${loc.lat},${loc.lng}" target="_blank" rel="noopener">mapa</a>` : ''}</dd>` : ''}
            <dt>${esc(t('priority'))}</dt><dd>${esc(t('prio.' + tk.priority))}</dd>
            <dt>${esc(t('due'))}</dt><dd>${esc(fmt(tk.due_at))}</dd>
            <dt>${esc(t('createdAt'))}</dt><dd>${esc(fmt(tk.created_at))} · ${esc(t('sources.' + tk.source))}</dd>
            ${tk.reporter ? `<dt>${esc(t('reporter'))}</dt><dd>${esc([tk.reporter.name, tk.reporter.phone, tk.reporter.email].filter(Boolean).join(' · ') || '—')}</dd>` : ''}
            ${tk.closed_at ? `<dt>${esc(t('closedAt'))}</dt><dd>${esc(fmt(tk.closed_at))}</dd>` : ''}
          </dl>
          ${tk.description ? `<div class="desc" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--line)">${esc(tk.description)}</div>` : ''}
          ${ticketPhotos.length ? `<div class="photos">${ticketPhotos.map((a) => `<div class="photo"><img data-att="${esc(a.uuid)}" alt="">
            <label>${manage ? `<input type="checkbox" data-vis="${esc(a.uuid)}" ${a.customer_visible ? 'checked' : ''}>` : ''} ${esc(t('forCustomer'))}</label></div>`).join('')}</div>` : ''}
        </div>

        <div class="card">
          <h2>${esc(t('assignment'))}</h2>
          <p style="margin-top:0">${esc(t('technician'))}: <strong>${esc(tk.technician?.name || '—')}</strong>${tk.assigned_at ? ` <span class="muted small">(${esc(fmt(tk.assigned_at))})</span>` : ''}</p>
          ${manage && open ? `
            <div class="btn-row"><select id="as-tech" style="flex:1;min-width:180px"></select><button class="btn primary" id="btn-assign">${esc(tk.technician ? t('reassign') : t('assign'))}</button></div>
            <div class="muted small" id="as-note"></div>
            <div class="btn-row" style="margin-top:14px">
              ${tk.status !== 'waiting' ? `<button class="btn" data-st="waiting">⏸ ${esc(t('setWaiting'))}</button>` : `<button class="btn" data-st="${tk.technician ? 'assigned' : 'new'}">▶ ${esc(t('reopen'))}</button>`}
              <button class="btn danger" data-st="cancelled">✕ ${esc(t('setCancelled'))}</button>
            </div>` : ''}
          ${manage && tk.status === 'cancelled' ? `<button class="btn" data-st="new">▶ ${esc(t('reopen'))}</button>` : ''}
        </div>

        ${history ? `<div class="card"><h2>${esc(t('history'))}</h2><div class="history">${history}</div></div>` : ''}
      </div>
      <div>
        ${protocols ? `<div class="card"><h2>${esc(t('protocols'))}</h2>${protocols}</div>` : ''}
        <div class="card"><h2>${esc(t('visits'))}</h2>${visitsHtml || `<div class="muted">${esc(t('noVisits'))}</div>`}</div>
      </div>
    </div>`;

  // fotky
  el.querySelectorAll('img[data-att]').forEach((img) => {
    loadImg(img.dataset.att, img);
    img.onclick = () => openAuthed(`/api/v1/attachments/${img.dataset.att}/download`);
  });
  el.querySelectorAll('[data-vis]').forEach((cb) => {
    cb.onchange = async () => {
      try { await api('PATCH', `/attachments/${cb.dataset.vis}`, { customer_visible: cb.checked }); toast(t('saved')); } catch (e) { cb.checked = !cb.checked; toast(errText(e), true); }
    };
  });

  // přidělení
  if (manage && open) {
    const sel = el.querySelector('#as-tech');
    const techs = await technicians(tk.project?.id);
    sel.innerHTML = techs.list.map((u) => `<option value="${u.id}" ${tk.technician?.id === u.id ? 'selected' : ''}>${esc(u.name)}</option>`).join('');
    if (techs.fallback) el.querySelector('#as-note').textContent = t('noTechInProject');
    el.querySelector('#btn-assign').onclick = async () => {
      try { await api('POST', `/tickets/${id}/assign`, { technician_id: Number(sel.value) }); toast(t('assigned')); renderTicket(id); } catch (e) { toast(errText(e), true); }
    };
    el.querySelector('#btn-edit').onclick = () => editTicket(tk);
  }
  el.querySelectorAll('[data-st]').forEach((b) => {
    b.onclick = async () => {
      const to = b.dataset.st;
      let note = null;
      if (to === 'cancelled' && !(await confirmModal(t('confirmCancel')))) return;
      if (to === 'waiting' || to === 'cancelled') {
        note = await promptModal(t('note'));
        if (note === undefined) return;
      }
      try { await api('POST', `/tickets/${id}/status`, { status: to, note: note || null }); renderTicket(id); } catch (e) { toast(errText(e), true); }
    };
  });

  // návštěvy a protokoly
  el.querySelectorAll('[data-return]').forEach((b) => {
    b.onclick = async () => {
      const note = await promptModal(t('returnNote'), true);
      if (!note) return;
      try { await api('POST', `/visits/${b.dataset.return}/return`, { note }); renderTicket(id); } catch (e) { toast(errText(e), true); }
    };
  });
  el.querySelectorAll('[data-proto]').forEach((b) => {
    b.onclick = async () => {
      try {
        const res = await api('POST', `/visits/${b.dataset.v}/protocol`, { language: b.dataset.proto });
        await renderTicket(id);
        openAuthed(res.data.html_url);
      } catch (e) { toast(errText(e), true); }
    };
  });
  el.querySelectorAll('[data-preview]').forEach((b) => { b.onclick = () => openAuthed(b.dataset.preview); });
  el.querySelectorAll('[data-approve]').forEach((b) => {
    b.onclick = async () => {
      if (!(await confirmModal(t('confirmApprove')))) return;
      try { await api('POST', `/protocols/${b.dataset.approve}/approve`); toast(t('approved')); renderTicket(id); } catch (e) {
        toast(e.status === 409 ? t('conflict') : errText(e), true);
        if (e.status === 409) renderTicket(id);
      }
    };
  });
}

async function editTicket(tk) {
  const prj = (await projects()).find((p) => p.id === tk.project?.id);
  let loc = tk.location;
  await modal(`
    <h3>${esc(t('edit'))} ${esc(tk.number)}</h3>
    <form id="ef">
      <div class="form-grid">
        <div class="f full"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:4px">${esc(t('location'))}</span><div id="eloc"></div></div>
        <label class="f full"><span class="req">${esc(t('reqTitle'))}</span><input type="text" name="title" required value="${esc(tk.title)}"></label>
        <label class="f full"><span>${esc(t('description'))}</span><textarea name="description">${esc(tk.description || '')}</textarea></label>
        <label class="f"><span>${esc(t('serviceType'))}</span><select name="service_type">${(prj?.service_types || ['servis']).map((s) => `<option value="${s}" ${s === tk.service_type ? 'selected' : ''}>${esc(t('stype.' + s))}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('priority'))}</span><select name="priority">${['low', 'normal', 'high', 'urgent'].map((p) => `<option value="${p}" ${p === tk.priority ? 'selected' : ''}>${esc(t('prio.' + p))}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('due'))}</span><input type="datetime-local" name="due_at" value="${esc(toLocalInput(tk.due_at))}"></label>
      </div>
      <div class="banner err" id="eerr" hidden></div>
      <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div>
    </form>`, (d, close) => {
    locationPicker(d.querySelector('#eloc'), () => tk.project?.id, (l) => { loc = l; }, loc);
    const f = d.querySelector('#ef');
    f.onsubmit = async (e) => {
      e.preventDefault();
      try {
        await api('PATCH', `/tickets/${tk.id}`, {
          title: f.title.value.trim(), description: f.description.value.trim() || null,
          service_type: f.service_type.value, priority: f.priority.value, due_at: fromLocalInput(f.due_at.value),
          ...(loc ? { location_id: loc.id } : {}),
        });
        close(true);
        toast(t('saved'));
        renderTicket(tk.id);
      } catch (ex) {
        const err = d.querySelector('#eerr');
        err.textContent = errText(ex);
        err.hidden = false;
      }
    };
  });
}

// ================================================================ provozovny

async function renderLocations(q) {
  const el = shell('locations');
  const project = q.get('project') || '';
  const search = q.get('q') || '';
  const setQ = (c) => {
    const n = new URLSearchParams(q);
    Object.entries(c).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k)));
    location.hash = '#/locations?' + n.toString();
  };

  let prj, res;
  try {
    prj = await projects();
    const p = new URLSearchParams({ all: '1', per_page: '200' });
    if (project) p.set('project_id', project);
    if (search) p.set('q', search);
    res = await api('GET', '/locations?' + p.toString());
  } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  const pname = (id) => prj.find((p) => p.id === id)?.name || '';
  const canAdd = prj.some((p) => managesProject(p.id));

  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('locations'))}</h1>${canAdd ? `<button class="btn primary" id="btn-add">➕ ${esc(t('newLocation'))}</button>` : ''}</div>
    <div class="filters">
      <select id="f-project"><option value="">${esc(t('allProjects'))}</option>${prj.map((p) => `<option value="${p.id}" ${String(p.id) === project ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>
      <input type="search" id="f-q" placeholder="${esc(t('filter'))}" value="${esc(search)}">
    </div>
    ${res.data.length ? `<div class="table-wrap"><table>
      <thead><tr><th>${esc(t('code'))}</th><th>${esc(t('name'))}</th><th>${esc(t('address'))}</th><th>${esc(t('project'))}</th><th></th></tr></thead>
      <tbody>${res.data.map((l, i) => `<tr data-i="${i}"><td class="num">${esc(l.code)}</td><td>${esc(l.name)}</td><td>${esc(addrOf(l))}</td><td>${esc(pname(l.project_id))}</td>
        <td>${l.is_active ? '' : chip('off', t('inactive'))}</td></tr>`).join('')}</tbody>
    </table></div>
    ${res.meta?.total > res.data.length ? `<p class="muted small">${res.data.length} / ${res.meta.total}</p>` : ''}` : `<div class="card empty">—</div>`}`;

  el.querySelector('#f-project').onchange = (e) => setQ({ project: e.target.value });
  el.querySelector('#f-q').addEventListener('input', debounce((e) => setQ({ q: e.target.value.trim() }), 450));
  if (search) { const i = el.querySelector('#f-q'); i.focus(); i.setSelectionRange(search.length, search.length); }
  el.querySelector('#btn-add')?.addEventListener('click', () => editLocation(null, prj, project));
  el.querySelectorAll('tr[data-i]').forEach((tr) => {
    const l = res.data[Number(tr.dataset.i)];
    tr.onclick = () => (managesProject(l.project_id) ? editLocation(l, prj) : null);
  });
}

async function editLocation(l, prj, defaultProject) {
  const managed = prj.filter((p) => managesProject(p.id));
  const cur = l || { project_id: Number(defaultProject) || managed[0]?.id, country: 'CZ', is_active: true };
  const ok = await modal(`
    <h3>${esc(l ? l.code + ' · ' + l.name : t('newLocation'))}</h3>
    <form id="lf2">
      <div class="form-grid">
        <label class="f"><span class="req">${esc(t('project'))}</span><select name="project_id" ${l ? 'disabled' : ''}>${managed.map((p) => `<option value="${p.id}" ${p.id === cur.project_id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('endCustomer'))}</span><select name="end_customer_id"></select></label>
        <label class="f"><span class="req">${esc(t('code'))}</span><input type="text" name="code" required value="${esc(cur.code || '')}"></label>
        <label class="f"><span class="req">${esc(t('name'))}</span><input type="text" name="name" required value="${esc(cur.name || '')}"></label>
        <label class="f full"><span>${esc(t('street'))}</span><input type="text" name="street" value="${esc(cur.street || '')}"></label>
        <label class="f"><span>${esc(t('city'))}</span><input type="text" name="city" value="${esc(cur.city || '')}"></label>
        <label class="f"><span>${esc(t('zip'))}</span><input type="text" name="zip" value="${esc(cur.zip || '')}"></label>
        <label class="f"><span>${esc(t('country'))}</span><select name="country">${['CZ', 'PL', 'DE', 'SK'].map((c) => `<option ${c === cur.country ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
        <label class="f"><span>${esc(t('active'))}</span><select name="is_active"><option value="1" ${cur.is_active ? 'selected' : ''}>${esc(t('yes'))}</option><option value="0" ${!cur.is_active ? 'selected' : ''}>${esc(t('no'))}</option></select></label>
        <label class="f"><span>${esc(t('lat'))}</span><input type="number" step="any" name="lat" value="${cur.lat ?? ''}"></label>
        <label class="f"><span>${esc(t('lng'))}</span><input type="number" step="any" name="lng" value="${cur.lng ?? ''}"></label>
        <label class="f full"><span>${esc(t('note'))}</span><textarea name="note">${esc(cur.note || '')}</textarea></label>
      </div>
      <div class="banner err" id="lerr2" hidden></div>
      <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div>
    </form>`, (d, close) => {
    const f = d.querySelector('#lf2');
    const fillEc = () => {
      const p = prj.find((x) => String(x.id) === String(f.project_id.value));
      f.end_customer_id.innerHTML = `<option value="">—</option>` + (p?.end_customers || []).map((ec) => `<option value="${ec.id}" ${ec.id === cur.end_customer_id ? 'selected' : ''}>${esc(ec.name)}</option>`).join('');
    };
    f.project_id.onchange = fillEc;
    fillEc();
    f.onsubmit = async (e) => {
      e.preventDefault();
      const num = (v) => (v === '' ? null : Number(v));
      const body = {
        end_customer_id: num(f.end_customer_id.value), code: f.code.value.trim(), name: f.name.value.trim(),
        street: f.street.value.trim() || null, city: f.city.value.trim() || null, zip: f.zip.value.trim() || null,
        country: f.country.value, is_active: f.is_active.value === '1', lat: num(f.lat.value), lng: num(f.lng.value),
        note: f.note.value.trim() || null,
      };
      try {
        if (l) await api('PATCH', `/locations/${l.id}`, body);
        else await api('POST', '/locations', { ...body, project_id: Number(f.project_id.value) });
        close(true);
      } catch (ex) {
        const err = d.querySelector('#lerr2');
        err.textContent = errText(ex);
        err.hidden = false;
      }
    };
  });
  if (ok) { toast(t('saved')); route(); }
}

// ================================================================ uživatelé (admin)

async function renderUsers() {
  const el = shell('users');
  let users, prj;
  try {
    [users, prj] = await Promise.all([api('GET', '/admin/users').then((r) => r.data), projects()]);
  } catch (e) { el.innerHTML = `<div class="banner err">${esc(errText(e))}</div>`; return; }

  el.innerHTML = `
    <div class="page-head"><h1>${esc(t('users'))}</h1><button class="btn primary" id="btn-add">➕ ${esc(t('newUser'))}</button></div>
    <div class="table-wrap"><table>
      <thead><tr><th>${esc(t('name'))}</th><th>${esc(t('email'))}</th><th>${esc(t('roles'))}</th><th>${esc(t('projects'))}</th><th></th></tr></thead>
      <tbody>${users.map((u, i) => `<tr data-i="${i}">
        <td><strong>${esc(u.name)}</strong>${u.phone ? `<div class="muted small">${esc(u.phone)}</div>` : ''}</td>
        <td>${esc(u.email)}</td>
        <td>${u.roles.map((r) => chip('role', t('role.' + r))).join('')}</td>
        <td class="small">${u.project_ids.map((id) => esc(prj.find((p) => p.id === id)?.name || id)).join(', ')}${u.location_ids.length ? ` <span class="muted">· ${u.location_ids.length} ${esc(t('locations').toLowerCase())}</span>` : ''}</td>
        <td>${u.is_active ? '' : chip('off', t('inactive'))} <span class="muted small">${esc(u.locale.toUpperCase())}</span></td>
      </tr>`).join('')}</tbody>
    </table></div>`;

  el.querySelector('#btn-add').onclick = () => editUser(null, prj);
  el.querySelectorAll('tr[data-i]').forEach((tr) => { tr.onclick = () => editUser(users[Number(tr.dataset.i)], prj); });
}

async function editUser(u, prj) {
  const cur = u || { roles: ['technik'], project_ids: [], location_ids: [], locale: 'cs', is_active: true };
  let allLocs = [];
  try { allLocs = (await api('GET', '/locations?all=1&per_page=500')).data; } catch { /* nic */ }

  const ok = await modal(`
    <h3>${esc(u ? u.name : t('newUser'))}</h3>
    <form id="uf">
      <div class="form-grid">
        <label class="f"><span class="req">${esc(t('name'))}</span><input type="text" name="name" required value="${esc(cur.name || '')}"></label>
        <label class="f"><span class="req">${esc(t('email'))}</span><input type="email" name="email" required value="${esc(cur.email || '')}"></label>
        <label class="f"><span>${esc(t('phone'))}</span><input type="tel" name="phone" value="${esc(cur.phone || '')}"></label>
        <label class="f"><span>${esc(t('language'))}</span><select name="locale"><option value="cs" ${cur.locale === 'cs' ? 'selected' : ''}>Čeština</option><option value="pl" ${cur.locale === 'pl' ? 'selected' : ''}>Polski</option></select></label>
        <label class="f"><span class="${u ? '' : 'req'}">${esc(u ? t('newPassword') : t('password'))}</span><input type="text" name="password" ${u ? '' : 'required'} minlength="8" autocomplete="new-password"></label>
        <label class="f"><span>${esc(t('active'))}</span><select name="is_active"><option value="1" ${cur.is_active ? 'selected' : ''}>${esc(t('yes'))}</option><option value="0" ${!cur.is_active ? 'selected' : ''}>${esc(t('no'))}</option></select></label>
        <div class="f full"><span class="req" style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px">${esc(t('roles'))}</span>
          <div class="checks">${['technik', 'dispecer', 'admin', 'spolecnik', 'ekonom', 'zakaznik'].map((r) => `<label><input type="checkbox" name="roles" value="${r}" ${cur.roles.includes(r) ? 'checked' : ''}> ${esc(t('role.' + r))}</label>`).join('')}</div></div>
        <div class="f full"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px">${esc(t('projects'))}</span>
          <div class="checks">${prj.map((p) => `<label><input type="checkbox" name="projects" value="${p.id}" ${cur.project_ids.includes(p.id) ? 'checked' : ''}> ${esc(p.name)}</label>`).join('')}</div></div>
        <div class="f full" id="cust-box"><span style="display:block;font-size:13px;color:var(--muted);font-weight:600;margin-bottom:6px">${esc(t('customerLocations'))}</span>
          <input type="search" id="loc-filter" placeholder="${esc(t('filter'))}" style="margin-bottom:6px">
          <div class="check-list">${allLocs.map((l) => `<label data-txt="${esc((l.code + ' ' + l.name + ' ' + (l.city || '')).toLowerCase())}"><input type="checkbox" name="locs" value="${l.id}" ${cur.location_ids.includes(l.id) ? 'checked' : ''}> <span><strong>${esc(l.code)}</strong> · ${esc(l.name)} <span class="muted small">${esc(l.city || '')}</span></span></label>`).join('')}</div></div>
      </div>
      <div class="banner err" id="uerr" hidden></div>
      <div class="modal-foot"><button type="button" class="btn" data-close>${esc(t('cancel'))}</button><button class="btn primary">${esc(t('save'))}</button></div>
    </form>`, (d, close) => {
    const f = d.querySelector('#uf');
    const custBox = d.querySelector('#cust-box');
    const syncCust = () => { custBox.hidden = !d.querySelector('input[name=roles][value=zakaznik]').checked; };
    d.querySelectorAll('input[name=roles]').forEach((c) => { c.onchange = syncCust; });
    syncCust();
    d.querySelector('#loc-filter').oninput = (e) => {
      const v = e.target.value.toLowerCase();
      d.querySelectorAll('.check-list label').forEach((lb) => { lb.hidden = v && !lb.dataset.txt.includes(v); });
    };
    f.onsubmit = async (e) => {
      e.preventDefault();
      const checked = (name) => [...d.querySelectorAll(`input[name=${name}]:checked`)].map((c) => c.value);
      const body = {
        name: f.name.value.trim(), email: f.email.value.trim(), phone: f.phone.value.trim() || null,
        locale: f.locale.value, is_active: f.is_active.value === '1',
        roles: checked('roles'), project_ids: checked('projects').map(Number), location_ids: checked('locs').map(Number),
      };
      if (f.password.value) body.password = f.password.value;
      try {
        if (u) await api('PATCH', `/admin/users/${u.id}`, body);
        else await api('POST', '/admin/users', body);
        close(true);
      } catch (ex) {
        const err = d.querySelector('#uerr');
        err.textContent = errText(ex);
        err.hidden = false;
      }
    };
  });
  if (ok) { techCache.clear(); toast(t('saved')); renderUsers(); }
}

// ================================================================ start

window.addEventListener('hashchange', route);
route();

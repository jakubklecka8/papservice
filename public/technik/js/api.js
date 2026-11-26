// Volání API /api/v1 s tokenem. Rozlišuje výpadek sítě (NetworkError)
// a odpověď serveru s chybou (ApiError se status kódem).

const BASE = '/api/v1';
let token = null;

export class NetworkError extends Error {}
export class ApiError extends Error {
  constructor(status, data) {
    super((data && data.message) || `HTTP ${status}`);
    this.status = status;
    this.data = data;
  }
}

export function setToken(t) { token = t; }
export function getToken() { return token; }

export async function request(method, path, body, { timeout = 25000, raw = false } = {}) {
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;

  let payload;
  if (body instanceof FormData) {
    payload = body;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeout);
  let res;
  try {
    res = await fetch(BASE + path, { method, headers, body: payload, signal: ctrl.signal, cache: 'no-store' });
  } catch (e) {
    throw new NetworkError(e.message || 'network');
  } finally {
    clearTimeout(timer);
  }

  if (raw && res.ok) return res;

  let data = null;
  const text = await res.text();
  if (text) {
    try { data = JSON.parse(text); } catch { data = { message: text.slice(0, 200) }; }
  }
  if (!res.ok) throw new ApiError(res.status, data);
  return data;
}

export const api = {
  login: (email, password) => request('POST', '/auth/login', { email, password, device_name: 'PWA ' + (navigator.platform || 'mobil') }),
  logout: () => request('POST', '/auth/logout'),
  me: () => request('GET', '/auth/me'),
  myTickets: () => request('GET', '/tickets?mine=1&open=1&per_page=200'),
  ticket: (id) => request('GET', `/tickets/${id}`),
  upsertVisit: (ticketId, uuid, data) => request('PUT', `/tickets/${ticketId}/visits/${uuid}`, data),
  submitVisit: (uuid) => request('POST', `/visits/${uuid}/submit`),
  setStatus: (ticketId, status, note) => request('POST', `/tickets/${ticketId}/status`, { status, note }),
  uploadPhoto: (form) => request('POST', '/attachments', form, { timeout: 120000 }),
  instructions: () => request('GET', '/instructions'),
  rateInstruction: (versionId, body) => request('POST', `/instruction-versions/${versionId}/rate`, body),
  expenses: () => request('GET', '/expenses?mine=1'),
  saveExpense: (form, id) => request('POST', id ? `/expenses/${id}` : '/expenses', form, { timeout: 120000 }),
  deleteExpense: (id) => request('DELETE', `/expenses/${id}`),
  receipt: (id) => request('GET', `/expenses/${id}/receipt`, undefined, { raw: true, timeout: 60000 }),
  earnings: (month) => request('GET', `/payroll/statement?month=${month}`),
  route: (date) => request('GET', `/routes?date=${date}`),
  catalog: () => request('GET', '/parts?per_page=2000'),
  myStock: () => request('GET', '/stock?warehouse_id=mine'),
  pushKey: () => request('GET', '/push/key'),
  pushSubscribe: (sub) => request('POST', '/push/subscribe', sub),
  pushUnsubscribe: (endpoint) => request('POST', '/push/unsubscribe', { endpoint }),
  pushTest: () => request('POST', '/push/test'),
  download: (uuid) => request('GET', `/attachments/${uuid}/download`, undefined, { raw: true, timeout: 60000 }),
};

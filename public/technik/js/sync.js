// Fronta změn (outbox). Všechno, co technik uloží, jde nejdřív sem a odesílá se,
// až je signál. Každý záznam má UUID z telefonu → opakované odeslání na serveru
// nic nezduplikuje (PUT návštěvy a nahrání fotky jsou idempotentní).

import { outbox } from './db.js';
import { api, ApiError, NetworkError } from './api.js';

export const syncEvents = new EventTarget();
let running = false;
let timer = null;
const COALESCE = new Set(['visit', 'status']);

function emit(type, detail = {}) {
  syncEvents.dispatchEvent(new CustomEvent(type, { detail }));
}

/** Přidá změnu do fronty. Návštěva/stav se slučuje s čekající verzí téhož záznamu. */
export async function enqueue(item) {
  item.created = item.created || new Date().toISOString();
  if (COALESCE.has(item.type)) {
    const items = await outbox.all();
    const same = items.find((i) => i.type === item.type && i.key === item.key && !i.error && !i.inflight);
    if (same) {
      await outbox.put({ ...same, payload: item.payload, created: item.created });
      emit('change');
      kick();
      return;
    }
  }
  await outbox.add(item);
  emit('change');
  kick();
}

export async function stats() {
  const items = await outbox.all();
  return {
    pending: items.filter((i) => !i.error).length,
    failed: items.filter((i) => i.error),
    items,
  };
}

export async function pendingFor(ticketId) {
  const items = await outbox.all();
  return items.filter((i) => String(i.ticketId) === String(ticketId));
}

export async function discard(id) {
  await outbox.del(id);
  emit('change');
}

export async function retryFailed() {
  const items = await outbox.all();
  for (const i of items.filter((x) => x.error)) {
    delete i.error;
    await outbox.put(i);
  }
  emit('change');
  kick();
}

/** Spustí odesílání (hned, ale ne souběžně). */
export function kick(delay = 300) {
  clearTimeout(timer);
  timer = setTimeout(process, delay);
}

async function send(item) {
  switch (item.type) {
    case 'visit':
      return api.upsertVisit(item.ticketId, item.key, item.payload);
    case 'submit':
      return api.submitVisit(item.key);
    case 'status':
      return api.setStatus(item.ticketId, item.payload.status, item.payload.note);
    case 'photo': {
      const form = new FormData();
      form.append('uuid', item.key);
      form.append('attachable_type', 'visit');
      form.append('attachable_id', item.visitUuid);
      form.append('kind', 'photo');
      form.append('file', item.blob, item.name || `${item.key}.jpg`);
      return api.uploadPhoto(form);
    }
    default:
      throw new ApiError(400, { message: 'Neznámý typ ' + item.type });
  }
}

async function process() {
  if (running) return;
  if (!navigator.onLine) { schedule(); return; }
  running = true;
  emit('start');

  try {
    const items = (await outbox.all()).sort((a, b) => a.id - b.id);
    for (const item of items) {
      if (item.error) continue;
      try {
        await send(item);
        await outbox.del(item.id);
        emit('sent', { item });
      } catch (e) {
        if (e instanceof NetworkError) { schedule(); break; }
        if (e instanceof ApiError && e.status === 401) { emit('auth'); break; }
        if (e instanceof ApiError && e.status === 409 && (item.type === 'visit' || item.type === 'submit')) {
          // na serveru už je novější stav (návštěva odeslaná / schválená) – změnu zahodit
          await outbox.del(item.id);
          emit('conflict', { item, message: e.message });
          continue;
        }
        if (e instanceof ApiError && e.status >= 500) { schedule(60000); break; }
        item.error = e.message || 'Chyba';
        item.errorStatus = e.status || 0;
        await outbox.put(item);
        emit('failed', { item });
      }
    }
  } finally {
    running = false;
    emit('change');
    emit('done');
  }
}

function schedule(ms = 20000) {
  clearTimeout(timer);
  timer = setTimeout(process, ms);
}

window.addEventListener('online', () => kick(500));
document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') kick(500); });
setInterval(() => kick(0), 60000);

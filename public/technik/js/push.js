// Push notifikace: přihlášení k odběru a odhlášení.

import { api } from './api.js';
import { kv } from './db.js';

export function pushSupported() {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

/** iPhone/iPad v Safari bez přidání na plochu push neumí */
export function needsInstallForPush() {
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  return ios && !standalone;
}

export function pushPermission() {
  return pushSupported() ? Notification.permission : 'unsupported';
}

function b64ToUint8(b64) {
  const pad = '='.repeat((4 - (b64.length % 4)) % 4);
  const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

/** Zapne upozornění (musí se volat z kliknutí uživatele kvůli dotazu na oprávnění). */
export async function enablePush() {
  if (!pushSupported()) return 'unsupported';
  const perm = await Notification.requestPermission();
  if (perm !== 'granted') return perm;
  // přihlášení k odběru jde přes servery Google/Apple – bez signálu by čekalo donekonečna
  await Promise.race([subscribe(), new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), 20000))]);
  return 'granted';
}

/** Přihlásí odběr na server (i opakovaně – po přihlášení jiného uživatele se odběr přepíše na něj). */
export async function subscribe() {
  const reg = await navigator.serviceWorker.ready;
  let sub = await reg.pushManager.getSubscription();
  if (!sub) {
    const { publicKey } = await api.pushKey();
    sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToUint8(publicKey) });
  }
  const json = sub.toJSON();
  await api.pushSubscribe({ endpoint: json.endpoint, keys: json.keys, content_encoding: 'aes128gcm' });
  await kv.set('pushEndpoint', json.endpoint);
  return sub;
}

/** Při startu aplikace obnoví registraci, pokud je oprávnění už dané */
export async function refreshPush() {
  if (pushPermission() !== 'granted') return;
  try { await subscribe(); } catch { /* offline – zkusí se příště */ }
}

/** Při odhlášení: server už nemá tomuto telefonu posílat upozornění */
export async function disablePushForLogout() {
  const endpoint = await kv.get('pushEndpoint');
  if (endpoint) {
    try { await api.pushUnsubscribe(endpoint); } catch { /* nic */ }
  }
}

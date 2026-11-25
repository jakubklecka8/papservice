// Malý obal nad IndexedDB.
//  kv     – přihlášení, uložené zakázky, rozpracované návštěvy
//  outbox – fronta změn čekajících na odeslání (pořadí = autoIncrement id)

const DB_NAME = 'pap-technik';
const DB_VERSION = 1;
let dbPromise = null;

function open() {
  if (!dbPromise) {
    dbPromise = new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = () => {
        const db = req.result;
        if (!db.objectStoreNames.contains('kv')) db.createObjectStore('kv');
        if (!db.objectStoreNames.contains('outbox')) db.createObjectStore('outbox', { keyPath: 'id', autoIncrement: true });
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }
  return dbPromise;
}

function wrap(req) {
  return new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function store(name, mode = 'readonly') {
  const db = await open();
  return db.transaction(name, mode).objectStore(name);
}

export const kv = {
  async get(key) { return wrap((await store('kv')).get(key)); },
  async set(key, value) { return wrap((await store('kv', 'readwrite')).put(value, key)); },
  async del(key) { return wrap((await store('kv', 'readwrite')).delete(key)); },
  async clear() { return wrap((await store('kv', 'readwrite')).clear()); },
};

export const outbox = {
  async all() { return wrap((await store('outbox')).getAll()); },
  async add(item) { return wrap((await store('outbox', 'readwrite')).add(item)); },
  async put(item) { return wrap((await store('outbox', 'readwrite')).put(item)); },
  async del(id) { return wrap((await store('outbox', 'readwrite')).delete(id)); },
  async clear() { return wrap((await store('outbox', 'readwrite')).clear()); },
};

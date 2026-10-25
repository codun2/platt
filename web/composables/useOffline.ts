/**
 * Offline-first core (no dependencies):
 *  - a tiny IndexedDB wrapper (`rakeez_<tenant>`, stores `kv` and `outbox`);
 *  - the outbox: writes made without internet, replayed in order when the
 *    connection returns, each with an Idempotency-Key so a retry never doubles;
 *  - connection state shown in the top bar.
 * Works without a service worker too (plain HTTP): the open page keeps selling.
 */
export interface OutboxOp {
  id: string; kind: string; method: 'POST' | 'PATCH' | 'DELETE'; path: string; body: any
  label: string; created_at: string; status: 'pending' | 'failed'; error?: string; attempts: number; result?: any
}

const state = reactive({ online: true, syncing: false, pending: 0, failed: 0, lastSync: '' as string, tenant: '' as string })
let dbp: Promise<IDBDatabase> | null = null

function openDb(): Promise<IDBDatabase> {
  if (dbp) return dbp
  dbp = new Promise((resolve, reject) => {
    const req = indexedDB.open(`rakeez_${state.tenant || 'x'}`, 1)
    req.onupgradeneeded = () => {
      const db = req.result
      if (!db.objectStoreNames.contains('kv')) db.createObjectStore('kv')
      if (!db.objectStoreNames.contains('outbox')) db.createObjectStore('outbox', { keyPath: 'id' })
    }
    req.onsuccess = () => resolve(req.result)
    req.onerror = () => reject(req.error)
  })
  return dbp
}
async function tx<T>(store: string, mode: IDBTransactionMode, fn: (s: IDBObjectStore) => IDBRequest<T> | void): Promise<T> {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const t = db.transaction(store, mode); const s = t.objectStore(store)
    const r = fn(s) as IDBRequest<T> | undefined
    t.oncomplete = () => resolve(r ? r.result : (undefined as any))
    t.onerror = () => reject(t.error)
  })
}

export const localKV = {
  get: <T = any>(k: string) => tx<T>('kv', 'readonly', s => s.get(k)).catch(() => undefined as any),
  set: (k: string, v: any) => tx('kv', 'readwrite', s => { s.put(JSON.parse(JSON.stringify(v)), k) }).catch(() => {}),
  del: (k: string) => tx('kv', 'readwrite', s => { s.delete(k) }).catch(() => {}),
}

export const outbox = {
  all: () => tx<OutboxOp[]>('outbox', 'readonly', s => s.getAll()).then(r => (r || []).sort((a, b) => a.created_at.localeCompare(b.created_at))).catch(() => [] as OutboxOp[]),
  async add(op: Omit<OutboxOp, 'id' | 'created_at' | 'status' | 'attempts'> & { id?: string }) {
    const full: OutboxOp = { id: op.id || `op_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`, created_at: new Date().toISOString(), status: 'pending', attempts: 0, ...op } as OutboxOp
    await tx('outbox', 'readwrite', s => { s.put(JSON.parse(JSON.stringify(full))) })
    await refreshCounts()
    return full
  },
  put: (op: OutboxOp) => tx('outbox', 'readwrite', s => { s.put(JSON.parse(JSON.stringify(op))) }),
  async remove(id: string) { await tx('outbox', 'readwrite', s => { s.delete(id) }); await refreshCounts() },
}

async function refreshCounts() {
  const ops = await outbox.all()
  state.pending = ops.filter(o => o.status === 'pending').length
  state.failed = ops.filter(o => o.status === 'failed').length
}

/** Listeners called after each successful upload (e.g. POS refreshes its pack). */
const onSynced: ((op: OutboxOp) => void)[] = []

/** Replay pending ops in order. Network trouble stops the run (retried later); a 4xx marks the op failed. */
export async function syncNow(): Promise<void> {
  if (state.syncing || !state.tenant) return
  state.syncing = true
  try {
    const api = useApi()
    for (const op of await outbox.all()) {
      if (op.status !== 'pending') continue
      try {
        const res = await api.raw(op.path, { method: op.method, body: op.body, headers: { 'Idempotency-Key': op.id } })
        op.result = res
        await outbox.remove(op.id)
        onSynced.forEach(f => { try { f(op) } catch { /* listener errors never stop the sync */ } })
      } catch (e: any) {
        if (e?.status === 0 || e?.status >= 500 || e?.status === 401 || e?.status === 419) { markOnline(e?.status !== 0); break }   // offline / server down: try later
        op.status = 'failed'; op.error = e?.message || String(e); op.attempts++
        await outbox.put(op)
      }
    }
    state.lastSync = new Date().toISOString()
  } finally {
    state.syncing = false
    await refreshCounts()
  }
}

export function markOnline(v: boolean) {
  const was = state.online
  state.online = v
  if (v && !was) syncNow()
}

let started = false
/** Called once after login (tenant known): open the tenant's local store and start syncing. */
export async function startOffline(tenantId: string | number) {
  const t = String(tenantId)
  if (state.tenant !== t) { state.tenant = t; dbp = null }
  await refreshCounts()
  if (started) return
  started = true
  state.online = navigator.onLine
  window.addEventListener('online', () => markOnline(true))
  window.addEventListener('offline', () => markOnline(false))
  setInterval(() => { if (state.pending) syncNow() }, 30000)
  syncNow()
}

export function useOffline() {
  return { state, syncNow, outbox, localKV, onSynced: (f: (op: OutboxOp) => void) => onSynced.push(f) }
}

/** Device-unique temporary number for documents made offline (e.g. OFF-251025-7K3Q). */
export function tempNumber(prefix = 'OFF') {
  const d = new Date(); const ymd = `${String(d.getFullYear()).slice(2)}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`
  return `${prefix}-${ymd}-${Math.random().toString(36).slice(2, 6).toUpperCase()}`
}

/** True when an error means "no connection / server unreachable" rather than a validation problem. */
export const isOfflineError = (e: any) => e?.status === 0 || e?.status === 503 && e?.offline || e?.offline === true

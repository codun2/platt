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
        // Ops referring to records created offline get their real ids now.
        const idmap: Record<string, any> = (await localKV.get('idmap')) || {}
        const swap = (v: string) => v.replace(TMP, t => (idmap[t] !== undefined ? String(idmap[t]) : t))
        const path = swap(op.path)
        const body = op.body ? JSON.parse(swap(JSON.stringify(op.body))) : op.body
        if (/tmp_[a-z0-9]+/.test(path)) continue   // its parent record failed to upload: keep waiting
        const res: any = await api.raw(path, { method: op.method, body, headers: { 'Idempotency-Key': op.id } })
        op.result = res
        if ((op as any).tmp_id && res?.id) { idmap[(op as any).tmp_id] = res.id; await localKV.set('idmap', idmap) }
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

/** Main list endpoints per module, fetched once per session so they are readable offline. */
const WARM: Record<string, string[]> = {
  crm: ['/app/customers', '/app/invoices', '/app/quotes'],
  inventory: ['/app/inventory/items', '/app/inventory/items/meta', '/app/inventory/suppliers', '/app/inventory/purchases', '/app/inventory/warehouses'],
  pos: ['/app/pos/sales', '/app/pos/shifts'],
  bookings: ['/app/bookings/list', '/app/bookings/units', '/app/bookings/services'],
  travel: ['/app/travel/trips', '/app/travel/travelers', '/app/travel/packages'],
  accounting: ['/app/accounting/accounts', '/app/accounting/journal', '/app/accounting/expenses'],
  hr: ['/app/hr/employees', '/app/hr/leaves', '/app/hr/payroll'],
  rental: ['/app/rental/summary', '/app/rental/assets', '/app/rental/contracts', '/app/rental/installments', '/app/rental/owners'],
  service: ['/app/service/summary', '/app/service/orders', '/app/service/technicians', '/app/service/catalog'],
  projects: ['/app/projects/summary', '/app/projects/items', '/app/projects/tasks', '/app/projects/timesheets', '/app/projects/people'],
  education: ['/app/education/summary', '/app/education/students', '/app/education/classes', '/app/education/fees', '/app/education/teachers'],
  manufacturing: ['/app/manufacturing/summary', '/app/manufacturing/bom', '/app/manufacturing/orders'],
  whatsapp: ['/app/whatsapp/audiences', '/app/whatsapp/templates'],
}
let warmed = ''
export async function warmUp(modules: string[]) {
  if (!navigator.serviceWorker?.controller || warmed === state.tenant || !state.online) return
  warmed = state.tenant
  const api = useApi()
  for (const m of ['crm', ...modules]) for (const p of WARM[m] || []) {
    try { await api.get(p) } catch { /* not allowed / offline: skip */ }
    await new Promise(r => setTimeout(r, 120))   // stay light on the server
  }
}

export function useOffline() {
  return { state, syncNow, outbox, localKV, onSynced: (f: (op: OutboxOp) => void) => onSynced.push(f) }
}

/**
 * Writes that may be queued while offline (path relative to /api, method).
 * Money movements and numbered financial documents are deliberately NOT here:
 * they need the server (sequence numbers, ledger) — except POS sales, which
 * have their own offline flow with a temporary receipt number.
 */
const QUEUE: [RegExp, string[], string][] = [
  [/^\/app\/customers(\/[\w-]+)?$/, ['POST', 'PATCH'], 'عميل'],
  [/^\/app\/inventory\/items(\/[\w-]+)?$/, ['POST', 'PATCH'], 'صنف'],
  [/^\/app\/inventory\/(warehouses|suppliers|categories|units)(\/[\w-]+)?$/, ['POST', 'PATCH'], 'مستودع / مورد / تصنيف / وحدة'],
  [/^\/app\/hr\/attendance\/bulk$/, ['POST'], 'حضور الموظفين'],
  [/^\/app\/education\/attendance$/, ['POST'], 'حضور الطلاب'],
  [/^\/app\/education\/students(\/[\w-]+)?$/, ['POST', 'PATCH'], 'طالب'],
  [/^\/app\/service\/orders(\/[\w-]+)?$/, ['POST', 'PATCH'], 'أمر عمل'],
  [/^\/app\/service\/orders\/[\w-]+\/status$/, ['POST'], 'حالة أمر عمل'],
  [/^\/app\/projects\/tasks(\/[\w-]+)?$/, ['POST', 'PATCH'], 'مهمة / جلسة'],
  [/^\/app\/projects\/tasks\/[\w-]+\/complete$/, ['POST'], 'نتيجة جلسة / مهمة'],
  [/^\/app\/projects\/timesheets$/, ['POST'], 'ساعات عمل'],
  [/^\/app\/rental\/(owners|assets)(\/[\w-]+)?$/, ['POST', 'PATCH'], 'سجل عقاري'],
  [/^\/app\/whatsapp\/log$/, ['POST'], 'سجل واتساب'],
]
/** POST to these collections creates a record that should appear in its list right away. */
const CREATES = ['/app/customers', '/app/inventory/items', '/app/inventory/warehouses', '/app/inventory/suppliers', '/app/inventory/categories', '/app/inventory/units', '/app/education/students', '/app/service/orders', '/app/projects/tasks', '/app/projects/timesheets', '/app/rental/owners', '/app/rental/assets']
export function queueRule(path: string, method: string) {
  return QUEUE.find(([re, methods]) => re.test(path) && methods.includes(method)) || null
}
const TMP = /tmp_[a-z0-9]+/g
const lastSeg = (path: string) => path.split('/').pop() || ''

/**
 * Save an offline write. Returns what the page receives instead of the server reply:
 * creates get a temporary id (tmp_…) so they show up in lists right away; an edit of
 * something created offline is folded into its pending create.
 */
export async function queueWrite(path: string, method: 'POST' | 'PATCH', body: any): Promise<any> {
  const rule = queueRule(path, method)!
  const id = lastSeg(path)
  if (method === 'PATCH' && id.startsWith('tmp_')) {
    const ops = await outbox.all()
    const create = ops.find(o => o.status === 'pending' && (o as any).tmp_id === id)
    if (create) {
      create.body = { ...create.body, ...body }; (create as any).optimistic = { ...(create as any).optimistic, ...body }
      await outbox.put(create)
      return (create as any).optimistic
    }
  }
  const isCreate = method === 'POST' && CREATES.includes(path)
  const tmp = isCreate ? `tmp_${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}` : null
  const optimistic = { ...(body || {}), ...(tmp ? { id: tmp } : { id }), _pending: true }
  await outbox.add({ kind: 'write', method, path, body, label: `${rule[2]} — ${body?.name || body?.title || body?.description || (tmp ? tt('جديد', 'new') : tt('تعديل', 'edit'))}`, ...(tmp ? { tmp_id: tmp, optimistic } : {}) } as any)
  return optimistic
}

/** Pending creates for a list endpoint (shown at the top of lists until uploaded). */
export async function pendingRowsFor(path: string): Promise<any[]> {
  if (!CREATES.includes(path) || !state.pending) return []
  return (await outbox.all()).filter(o => o.status === 'pending' && o.method === 'POST' && o.path === path && (o as any).optimistic).map(o => (o as any).optimistic)
}

/** Device-unique temporary number for documents made offline (e.g. OFF-251025-7K3Q). */
export function tempNumber(prefix = 'OFF') {
  const d = new Date(); const ymd = `${String(d.getFullYear()).slice(2)}${String(d.getMonth() + 1).padStart(2, '0')}${String(d.getDate()).padStart(2, '0')}`
  return `${prefix}-${ymd}-${Math.random().toString(36).slice(2, 6).toUpperCase()}`
}

/** True when an error means "no connection / server unreachable" rather than a validation problem. */
export const isOfflineError = (e: any) => e?.status === 0 || e?.status === 503 && e?.offline || e?.offline === true

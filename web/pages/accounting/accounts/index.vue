<script setup lang="ts">
definePageMeta({ title: 'شجرة الحسابات', titleEn: 'Chart of Accounts' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const rows = ref<any[]>([]); const from = ref(''); const to = ref(''); const q = ref('')
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ code: '', name: '', type: 'expense', parent_id: '', is_group: false, opening_balance: 0, is_active: true, description: '' })
const canManage = computed(() => auth.can('accounting.manage'))

const onlyActive = ref(false); const swapped = ref(false)
async function load() {
  // A start date after the end date: swap them instead of querying an empty range.
  swapped.value = false
  if (from.value && to.value && from.value > to.value) { [from.value, to.value] = [to.value, from.value]; swapped.value = true }
  rows.value = await api.get('/app/accounting/accounts', { from: from.value, to: to.value })
}
onMounted(load)
const hasPeriod = computed(() => !!(from.value || to.value))
function clearPeriod() { from.value = ''; to.value = ''; onlyActive.value = false; load() }
const periodMoves = computed(() => rows.value.filter(a => !a.is_group).reduce((n, a) => n + (+a.debit || 0) + (+a.credit || 0), 0))
// Tree order with depth for indentation.
const tree = computed(() => {
  const byParent: Record<string, any[]> = {}
  rows.value.forEach(a => { (byParent[a.parent_id || 0] ||= []).push(a) })
  const out: any[] = []
  const walk = (pid: any, depth: number) => (byParent[pid] || []).sort((a, b) => a.code.localeCompare(b.code)).forEach(a => { out.push({ ...a, depth }); walk(a.id, depth + 1) })
  walk(0, 0)
  let list = q.value ? out.filter(a => a.code.includes(q.value) || a.name.includes(q.value)) : out
  // «حركة في الفترة فقط»: hide accounts (and groups) that had nothing in the period.
  if (onlyActive.value && hasPeriod.value) {
    const moved = new Set(out.filter(a => !a.is_group && ((+a.debit || 0) || (+a.credit || 0))).map(a => a.id))
    const keep = new Set<any>()
    const byId: Record<string, any> = {}; out.forEach(a => { byId[a.id] = a })
    moved.forEach(id => { let a = byId[id]; while (a) { keep.add(a.id); a = byId[a.parent_id] } })
    list = list.filter(a => keep.has(a.id))
  }
  return list
})
const groups = computed(() => rows.value.filter(a => a.is_group))
function openNew(parent?: any) { editing.value = null; err.value = ''; Object.assign(form, { code: '', name: '', type: parent?.type || 'expense', parent_id: parent?.id || '', is_group: false, opening_balance: 0, is_active: true, description: '' }); modal.value = true }
function openEdit(a: any) { editing.value = a; err.value = ''; Object.assign(form, { code: a.code, name: a.name, type: a.type, parent_id: a.parent_id || '', is_group: !!a.is_group, opening_balance: +a.opening_balance || 0, is_active: !!a.is_active, description: a.description || '' }); modal.value = true }
async function save() {
  err.value = ''
  const body = { ...form, parent_id: form.parent_id || null, opening_balance: +form.opening_balance || 0 }
  try { editing.value ? await api.patch(`/app/accounting/accounts/${editing.value.id}`, body) : await api.post('/app/accounting/accounts', body); modal.value = false; await load() } catch (e: any) { err.value = e.message }
}
async function remove(a: any) { if (!await uiConfirm(tt(`حذف الحساب ${a.name}؟`,`Delete account ${a.name}?`))) return; try { const r = await api.del(`/app/accounting/accounts/${a.id}`); if (r.deactivated) uiAlert(r.message); await load() } catch (e: any) { uiAlert(e.message) } }
const typeTotal = (t: string) => rows.value.filter(a => a.type === t && !a.parent_id).reduce((s, a) => s + (+a.balance || 0), 0)
// Long lists are shown 25 per page.
const { rows: pRows, pager: pInfo, go: pGo } = usePaged(() => tree.value || [], 25)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/accounting">{{ tt('المحاسبة','Accounting') }}</NuxtLink> › <b>{{ tt('شجرة الحسابات','Chart of Accounts') }}</b></div>
    <PageHelp id="acc-accounts" :title="tt('شجرة الحسابات: أين يذهب كل ريال', 'Chart of accounts: where every riyal goes')">
      {{ tt('قائمة «الخانات» التي يُصنَّف فيها كل مال يدخل أو يخرج: الصندوق، البنك، المخزون، ديون العملاء، ديون الموردين، المبيعات، أنواع المصروفات… وبجانب كل خانة رصيدها الحالي.', 'The list of “buckets” every money movement is filed in: cash, bank, stock, customer and supplier balances, sales, each expense type… with its current balance.') }}
      <ul>
        <li>{{ tt('لا تحتاج أن تُدخل فيها شيئاً: البيع والمشتريات والرواتب والمصروفات تُسجَّل فيها تلقائياً.', 'You do not enter anything here: sales, purchases, payroll and expenses are filed automatically.') }}</li>
        <li>{{ tt('استخدمها لتعرف: كم في الصندوق والبنك؟ كم لك عند العملاء؟ كم عليك للموردين؟ — اضغط أي حساب لترى حركته.', 'Use it to see: how much is in cash and bank, owed by customers, owed to suppliers — open any account for its movements.') }}</li>
        <li>{{ tt('أضف حساباً فقط إذا احتجت تفصيلاً (مثل بنك ثانٍ أو نوع مصروف جديد).', 'Add an account only for more detail (a second bank, a new expense type).') }}</li>
      </ul>
    </PageHelp>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-building-bank" /></div><div><b>{{ f.money(typeTotal('asset')) }}</b><small>{{ tt('إجمالي الأصول','Total assets') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-file-dollar" /></div><div><b>{{ f.money(typeTotal('liability')) }}</b><small>{{ tt('إجمالي الالتزامات','Total liabilities') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-trending-up" /></div><div><b>{{ f.money(typeTotal('revenue')) }}</b><small>{{ tt('الإيرادات','Revenue') }}</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-trending-down" /></div><div><b>{{ f.money(typeTotal('expense')) }}</b><small>{{ tt('المصروفات','Expenses') }}</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="q" :placeholder="tt('بحث بالرمز أو الاسم','Search by code or name')" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" />
          <input v-model="from" type="date" dir="ltr" :title="tt('من تاريخ', 'From')" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /><span class="muted">{{ tt('إلى','to') }}</span><input v-model="to" type="date" dir="ltr" :title="tt('إلى تاريخ', 'To')" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
          <button v-if="hasPeriod" class="btn ghost sm" @click="clearPeriod"><i class="ti ti-x" /> {{ tt('مسح الفترة', 'Clear period') }}</button>
          <label v-if="hasPeriod" class="row" style="gap:6px;font-size:13px;cursor:pointer"><input v-model="onlyActive" type="checkbox" /> {{ tt('حركة في الفترة فقط', 'Only accounts with movement') }}</label>
        </div>
        <button v-if="canManage" class="btn" @click="openNew()"><i class="ti ti-plus" /> {{ tt('حساب جديد','New account') }}</button>
      </div>
      <div v-if="swapped" class="alert warn">{{ tt('تاريخ البداية كان بعد تاريخ النهاية، فتم تبديلهما.', 'The start date was after the end date, so they were swapped.') }}</div>
      <div v-if="hasPeriod && rows.length && !periodMoves" class="alert info"><i class="ti ti-info-circle" /> {{ tt('لا توجد أي حركة في هذه الفترة. القائمة تعرض كل الحسابات دائماً؛ عمودا مدين/دائن يخصّان الفترة، والرصيد هو رصيد الحساب حتى نهايتها.', 'No movement at all in this period. The list always shows every account; debit/credit are the period movements and the balance is the account balance at the end of it.') }}</div>
      <table class="table">
        <thead><tr><th>{{ tt('الرمز','Code') }}</th><th>{{ tt('الحساب','Account') }}</th><th>{{ tt('النوع','Type') }}</th><th>{{ tt('مدين (الفترة)','Debit (period)') }}</th><th>{{ tt('دائن (الفترة)','Credit (period)') }}</th><th>{{ to ? tt('الرصيد حتى', 'Balance at') + ' ' + to : tt('الرصيد','Balance') }}</th><th>{{ tt('الحالة','Status') }}</th><th></th></tr></thead>
        <tbody>
          <tr v-for="a in pRows" :key="a.id" :style="a.is_group ? 'background:#f8fafc' : ''">
            <td dir="ltr" style="text-align:right"><b>{{ a.code }}</b></td>
            <td :style="`padding-right:${12 + a.depth * 22}px`"><NuxtLink v-if="!a.is_group" :to="`/accounting/accounts/${a.id}`" style="color:var(--info);font-weight:700">{{ a.name }}</NuxtLink><b v-else>{{ a.name }}</b><small v-if="a.system_key" class="muted"> · {{ tt('نظامي','system') }}</small></td>
            <td><span class="badge" :class="accountTypes[a.type]?.cls">{{ accountTypes[a.type]?.label }}</span></td>
            <td class="num">{{ a.is_group ? '' : f.money(a.debit) }}</td>
            <td class="num">{{ a.is_group ? '' : f.money(a.credit) }}</td>
            <td class="num"><b>{{ f.money(a.balance) }}</b></td>
            <td><span class="badge" :class="a.is_active ? 'ok' : 'muted'">{{ a.is_active ? tt('نشط','Active') : tt('موقوف','Inactive') }}</span></td>
            <td class="row" style="gap:4px"><template v-if="canManage"><button v-if="a.is_group" class="btn ghost sm icon" :title="tt('حساب فرعي','Sub-account')" @click="openNew(a)"><i class="ti ti-plus" /></button><button class="btn ghost sm icon" @click="openEdit(a)"><i class="ti ti-pencil" /></button><button v-if="!a.system_key" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(a)"><i class="ti ti-trash" /></button></template></td>
          </tr>
        </tbody>
      </table>
      <UiPager :p="pInfo" @go="pGo" />
    </div>

    <UiModal v-if="modal" :title="editing ? tt('تعديل حساب','Edit account') : tt('حساب جديد','New account')" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('الرمز','Code') }}</label><input v-model="form.code" dir="ltr" :placeholder="tt('مثال: 5310','e.g. 5310')" /></div>
        <div class="field"><label>{{ tt('النوع','Type') }}</label><UiSelect v-model="form.type" :disabled="!!editing?.system_key"><option v-for="(t, k) in accountTypes" :key="k" :value="k">{{ t.label }}</option></UiSelect></div>
      </div>
      <div class="field"><label>{{ tt('اسم الحساب','Account name') }}</label><input v-model="form.name" /></div>
      <div class="field"><label>{{ tt('الحساب الأب','Parent account') }}</label><UiSelect v-model="form.parent_id"><option value="">{{ tt('— بدون —','— none —') }}</option><option v-for="g in groups" :key="g.id" :value="g.id">{{ g.code }} — {{ g.name }}</option></UiSelect></div>
      <div class="grid grid-2">
        <div class="field"><label>{{ tt('رصيد افتتاحي','Opening balance') }}</label><input v-model.number="form.opening_balance" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>{{ tt('الوصف','Description') }}</label><input v-model="form.description" /></div>
      </div>
      <div class="row"><label class="switch"><input v-model="form.is_group" type="checkbox" /><span /> {{ tt('حساب رئيسي (مجموعة)','Group account') }}</label><label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> {{ tt('نشط','Active') }}</label></div>
      <template #footer><button class="btn ghost" @click="modal = false">{{ tt('إلغاء','Cancel') }}</button><button class="btn" @click="save">{{ tt('حفظ','Save') }}</button></template>
    </UiModal>
  </div>
</template>

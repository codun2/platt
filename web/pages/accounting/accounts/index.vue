<script setup lang="ts">
definePageMeta({ title: 'شجرة الحسابات', titleEn: 'Chart of Accounts' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const rows = ref<any[]>([]); const from = ref(''); const to = ref(''); const q = ref('')
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ code: '', name: '', type: 'expense', parent_id: '', is_group: false, opening_balance: 0, is_active: true, description: '' })
const canManage = computed(() => auth.can('accounting.manage'))

async function load() { rows.value = await api.get('/app/accounting/accounts', { from: from.value, to: to.value }) }
onMounted(load)
// Tree order with depth for indentation.
const tree = computed(() => {
  const byParent: Record<string, any[]> = {}
  rows.value.forEach(a => { (byParent[a.parent_id || 0] ||= []).push(a) })
  const out: any[] = []
  const walk = (pid: any, depth: number) => (byParent[pid] || []).sort((a, b) => a.code.localeCompare(b.code)).forEach(a => { out.push({ ...a, depth }); walk(a.id, depth + 1) })
  walk(0, 0)
  return q.value ? out.filter(a => a.code.includes(q.value) || a.name.includes(q.value)) : out
})
const groups = computed(() => rows.value.filter(a => a.is_group))
function openNew(parent?: any) { editing.value = null; err.value = ''; Object.assign(form, { code: '', name: '', type: parent?.type || 'expense', parent_id: parent?.id || '', is_group: false, opening_balance: 0, is_active: true, description: '' }); modal.value = true }
function openEdit(a: any) { editing.value = a; err.value = ''; Object.assign(form, { code: a.code, name: a.name, type: a.type, parent_id: a.parent_id || '', is_group: !!a.is_group, opening_balance: +a.opening_balance || 0, is_active: !!a.is_active, description: a.description || '' }); modal.value = true }
async function save() {
  err.value = ''
  const body = { ...form, parent_id: form.parent_id || null, opening_balance: +form.opening_balance || 0 }
  try { editing.value ? await api.patch(`/app/accounting/accounts/${editing.value.id}`, body) : await api.post('/app/accounting/accounts', body); modal.value = false; await load() } catch (e: any) { err.value = e.message }
}
async function remove(a: any) { if (!confirm(`حذف الحساب ${a.name}؟`)) return; try { const r = await api.del(`/app/accounting/accounts/${a.id}`); if (r.deactivated) alert(r.message); await load() } catch (e: any) { alert(e.message) } }
const typeTotal = (t: string) => rows.value.filter(a => a.type === t && !a.parent_id).reduce((s, a) => s + (+a.balance || 0), 0)
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/accounting">المحاسبة</NuxtLink> › <b>شجرة الحسابات</b></div>
    <div class="grid grid-4" style="margin-bottom:16px">
      <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-building-bank" /></div><div><b>{{ f.money(typeTotal('asset')) }}</b><small>إجمالي الأصول</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-file-dollar" /></div><div><b>{{ f.money(typeTotal('liability')) }}</b><small>إجمالي الالتزامات</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-trending-up" /></div><div><b>{{ f.money(typeTotal('revenue')) }}</b><small>الإيرادات</small></div></div>
      <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-trending-down" /></div><div><b>{{ f.money(typeTotal('expense')) }}</b><small>المصروفات</small></div></div>
    </div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="q" placeholder="بحث بالرمز أو الاسم" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px;min-width:200px" />
          <input v-model="from" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" /><span class="muted">إلى</span><input v-model="to" type="date" dir="ltr" style="padding:7px 10px;border:1px solid var(--line);border-radius:10px" @change="load" />
        </div>
        <button v-if="canManage" class="btn" @click="openNew()"><i class="ti ti-plus" /> حساب جديد</button>
      </div>
      <table class="table">
        <thead><tr><th>الرمز</th><th>الحساب</th><th>النوع</th><th>مدين (الفترة)</th><th>دائن (الفترة)</th><th>الرصيد</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="a in tree" :key="a.id" :style="a.is_group ? 'background:#f8fafc' : ''">
            <td dir="ltr" style="text-align:right"><b>{{ a.code }}</b></td>
            <td :style="`padding-right:${12 + a.depth * 22}px`"><NuxtLink v-if="!a.is_group" :to="`/accounting/accounts/${a.id}`" style="color:var(--info);font-weight:700">{{ a.name }}</NuxtLink><b v-else>{{ a.name }}</b><small v-if="a.system_key" class="muted"> · نظامي</small></td>
            <td><span class="badge" :class="accountTypes[a.type]?.cls">{{ accountTypes[a.type]?.label }}</span></td>
            <td class="num">{{ a.is_group ? '' : f.money(a.debit) }}</td>
            <td class="num">{{ a.is_group ? '' : f.money(a.credit) }}</td>
            <td class="num"><b>{{ f.money(a.balance) }}</b></td>
            <td><span class="badge" :class="a.is_active ? 'ok' : 'muted'">{{ a.is_active ? 'نشط' : 'موقوف' }}</span></td>
            <td class="row" style="gap:4px"><template v-if="canManage"><button v-if="a.is_group" class="btn ghost sm icon" title="حساب فرعي" @click="openNew(a)"><i class="ti ti-plus" /></button><button class="btn ghost sm icon" @click="openEdit(a)"><i class="ti ti-pencil" /></button><button v-if="!a.system_key" class="btn ghost sm icon" style="color:var(--primary)" @click="remove(a)"><i class="ti ti-trash" /></button></template></td>
          </tr>
        </tbody>
      </table>
    </div>

    <UiModal v-if="modal" :title="editing ? 'تعديل حساب' : 'حساب جديد'" @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-2">
        <div class="field"><label>الرمز</label><input v-model="form.code" dir="ltr" placeholder="مثال: 5310" /></div>
        <div class="field"><label>النوع</label><UiSelect v-model="form.type" :disabled="!!editing?.system_key"><option v-for="(t, k) in accountTypes" :key="k" :value="k">{{ t.label }}</option></UiSelect></div>
      </div>
      <div class="field"><label>اسم الحساب</label><input v-model="form.name" /></div>
      <div class="field"><label>الحساب الأب</label><UiSelect v-model="form.parent_id"><option value="">— بدون —</option><option v-for="g in groups" :key="g.id" :value="g.id">{{ g.code }} — {{ g.name }}</option></UiSelect></div>
      <div class="grid grid-2">
        <div class="field"><label>رصيد افتتاحي</label><input v-model.number="form.opening_balance" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>الوصف</label><input v-model="form.description" /></div>
      </div>
      <div class="row"><label class="switch"><input v-model="form.is_group" type="checkbox" /><span /> حساب رئيسي (مجموعة)</label><label class="switch"><input v-model="form.is_active" type="checkbox" /><span /> نشط</label></div>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" @click="save">حفظ</button></template>
    </UiModal>
  </div>
</template>

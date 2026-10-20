<script setup lang="ts">
definePageMeta({ title: 'المشتريات' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const page = ref<any>(null); const status = ref(''); const q = ref('')
const suppliers = ref<any[]>([]); const meta = ref<any>({ warehouses: [] })
const modal = ref(false); const editing = ref<any>(null); const err = ref('')
const form = reactive<any>({ supplier_id: '', warehouse_id: '', date: f.today(), supplier_invoice: '', discount: 0, tax: 0, paid: 0, note: '', receive: true, lines: [] as any[] })
const iq = ref(''); const found = ref<any[]>([])
const view = ref<any>(null); const payAmt = ref(0)
const canManage = computed(() => auth.can('inventory.manage'))

async function load() { page.value = await api.get('/app/inventory/purchases', { status: status.value, q: q.value }) }
onMounted(async () => { await load(); suppliers.value = await api.get('/app/inventory/suppliers'); meta.value = await api.get('/app/inventory/items/meta') })

function openNew() { editing.value = null; Object.assign(form, { supplier_id: '', warehouse_id: meta.value.warehouses.find((w: any) => w.is_default)?.id || '', date: f.today(), supplier_invoice: '', discount: 0, tax: 0, paid: 0, note: '', receive: true, lines: [] }); err.value = ''; modal.value = true }
function openEdit(p: any) { editing.value = p; Object.assign(form, { supplier_id: p.supplier_id || '', warehouse_id: p.warehouse_id, date: p.date, supplier_invoice: p.supplier_invoice || '', discount: Number(p.discount), tax: Number(p.tax), paid: Number(p.paid), note: p.note || '', receive: false, lines: p.items.map((l: any) => ({ item_id: l.item_id, name: l.item?.name, qty: Number(l.qty), unit_cost: Number(l.unit_cost) })) }); err.value = ''; modal.value = true; view.value = null }
async function search() { found.value = (await api.get('/app/inventory/items/lookup', { q: iq.value })).items }
function addLine(i: any) { const ex = form.lines.find((l: any) => l.item_id === i.id); ex ? ex.qty++ : form.lines.push({ item_id: i.id, name: i.name, qty: 1, unit_cost: Number(i.cost_price) }); found.value = []; iq.value = '' }
const subtotal = computed(() => form.lines.reduce((s: number, l: any) => s + l.qty * l.unit_cost, 0))
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0) + Number(form.tax || 0)))
async function save() {
  err.value = ''
  const body = { ...form, supplier_id: form.supplier_id || null, warehouse_id: form.warehouse_id || null }
  try { editing.value ? await api.patch(`/app/inventory/purchases/${editing.value.id}`, body) : await api.post('/app/inventory/purchases', body); modal.value = false; await load() }
  catch (e: any) { err.value = e.message }
}
async function show(p: any) { view.value = await api.get(`/app/inventory/purchases/${p.id}`); payAmt.value = Math.max(0, Number(view.value.total) - Number(view.value.paid)) }
async function act(path: string, body: any = {}) { try { await api.post(`/app/inventory/purchases/${view.value.id}/${path}`, body); await show(view.value); await load() } catch (e: any) { alert(e.message) } }
const stLabel: any = { draft: 'مسودة', received: 'مستلمة', cancelled: 'ملغاة' }; const stClass: any = { draft: 'warn', received: 'ok', cancelled: 'bad' }
</script>

<template>
  <div>
    <div class="breadcrumb"><NuxtLink to="/inventory">المخزون</NuxtLink> › <b>المشتريات</b></div>
    <div class="card">
      <div class="row between" style="margin-bottom:14px">
        <div class="row">
          <input v-model="q" placeholder="رقم / مورد" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @keyup.enter="load" />
          <select v-model="status" style="padding:8px 12px;border:1px solid var(--line);border-radius:10px" @change="load"><option value="">كل الحالات</option><option value="draft">مسودة</option><option value="received">مستلمة</option><option value="cancelled">ملغاة</option></select>
          <button class="btn ghost" @click="load"><i class="ti ti-search" /></button>
        </div>
        <button v-if="canManage" class="btn" @click="openNew"><i class="ti ti-plus" /> فاتورة شراء</button>
      </div>
      <table class="table">
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>المورد</th><th>المستودع</th><th>بنود</th><th>الإجمالي</th><th>المدفوع</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
          <tr v-for="p in page?.data" :key="p.id">
            <td><a href="#" style="color:var(--info);font-weight:700" @click.prevent="show(p)">{{ p.number }}</a></td><td>{{ f.d(p.date) }}</td><td>{{ p.supplier?.name || '—' }}</td><td>{{ p.warehouse?.name }}</td><td>{{ p.items_count }}</td>
            <td class="num"><b>{{ f.money(p.total) }}</b></td><td class="num">{{ f.money(p.paid) }}</td><td><span class="badge" :class="stClass[p.status]">{{ stLabel[p.status] }}</span></td>
            <td><button class="btn ghost sm icon" @click="show(p)"><i class="ti ti-eye" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="page && !page.data.length" class="empty">لا توجد فواتير شراء.</p>
    </div>

    <UiModal v-if="modal" :title="editing ? `تعديل ${editing.number}` : 'فاتورة شراء جديدة'" lg @close="modal = false">
      <div v-if="err" class="alert bad">{{ err }}</div>
      <div class="grid grid-3">
        <div class="field"><label>المورد</label><select v-model="form.supplier_id"><option value="">—</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
        <div class="field"><label>المستودع</label><select v-model="form.warehouse_id"><option v-for="w in meta.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></select></div>
        <div class="field"><label>التاريخ</label><input v-model="form.date" type="date" /></div>
        <div class="field"><label>رقم فاتورة المورد</label><input v-model="form.supplier_invoice" dir="ltr" /></div>
      </div>
      <div class="field"><label>إضافة صنف</label><div class="row"><input v-model="iq" placeholder="اسم / SKU / باركود ثم Enter" @keyup.enter="search" /><button class="btn ghost" @click="search"><i class="ti ti-search" /></button></div>
        <div v-if="found.length" class="chips"><span v-for="i in found" :key="i.id" class="chip" @click="addLine(i)">{{ i.name }} <small class="muted">{{ f.money(i.cost_price) }}</small></span></div>
      </div>
      <table class="table"><thead><tr><th>الصنف</th><th style="width:110px">الكمية</th><th style="width:130px">تكلفة الوحدة</th><th>الإجمالي</th><th></th></tr></thead>
        <tbody><tr v-for="(l, i) in form.lines" :key="l.item_id"><td>{{ l.name }}</td><td><input v-model.number="l.qty" type="number" step="0.001" dir="ltr" style="width:100%;padding:5px;border:1px solid var(--line);border-radius:6px" /></td><td><input v-model.number="l.unit_cost" type="number" step="0.001" dir="ltr" style="width:100%;padding:5px;border:1px solid var(--line);border-radius:6px" /></td><td class="num">{{ f.money(l.qty * l.unit_cost) }}</td><td><button class="btn ghost sm icon" style="color:var(--primary)" @click="form.lines.splice(i, 1)"><i class="ti ti-x" /></button></td></tr></tbody></table>
      <p v-if="!form.lines.length" class="empty">أضف أصنافاً للفاتورة.</p>
      <div class="grid grid-4 mt">
        <div class="field"><label>خصم</label><input v-model.number="form.discount" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>ضريبة (مبلغ)</label><input v-model.number="form.tax" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>المدفوع</label><input v-model.number="form.paid" type="number" step="0.001" dir="ltr" /></div>
        <div class="field"><label>الإجمالي</label><input :value="f.money(total)" disabled dir="ltr" /></div>
      </div>
      <div class="field"><label>ملاحظة</label><input v-model="form.note" /></div>
      <label v-if="!editing" class="switch"><input v-model="form.receive" type="checkbox" /><span /> استلام فوري (يدخل المخزون الآن)</label>
      <template #footer><button class="btn ghost" @click="modal = false">إلغاء</button><button class="btn" :disabled="!form.lines.length" @click="save">حفظ</button></template>
    </UiModal>

    <UiModal v-if="view" :title="`فاتورة شراء ${view.number}`" lg @close="view = null">
      <div class="row between" style="margin-bottom:10px">
        <div class="row" style="gap:24px"><div><small class="muted">المورد</small><br /><b>{{ view.supplier?.name || '—' }}</b></div><div><small class="muted">التاريخ</small><br /><b>{{ f.d(view.date) }}</b></div><div><small class="muted">المستودع</small><br /><b>{{ view.warehouse?.name }}</b></div><div><small class="muted">الحالة</small><br /><span class="badge" :class="stClass[view.status]">{{ stLabel[view.status] }}</span></div></div>
      </div>
      <table class="table"><thead><tr><th>الصنف</th><th>الكمية</th><th>تكلفة الوحدة</th><th>الإجمالي</th></tr></thead>
        <tbody><tr v-for="l in view.items" :key="l.id"><td>{{ l.item?.name }}</td><td class="num">{{ f.qty(l.qty) }}</td><td class="num">{{ f.money(l.unit_cost) }}</td><td class="num">{{ f.money(l.total) }}</td></tr></tbody></table>
      <div class="row end mt" style="gap:24px"><span>خصم: <b>{{ f.money(view.discount) }}</b></span><span>ضريبة: <b>{{ f.money(view.tax) }}</b></span><span>الإجمالي: <b style="font-size:16px">{{ f.money(view.total, true) }}</b></span><span>المدفوع: <b>{{ f.money(view.paid) }}</b></span></div>
      <template v-if="canManage" #footer>
        <template v-if="view.status === 'draft'"><button class="btn ghost" @click="openEdit(view)">تعديل</button><button class="btn ok" @click="act('receive')"><i class="ti ti-package-import" /> استلام للمخزون</button></template>
        <template v-if="view.status !== 'cancelled' && Number(view.paid) < Number(view.total)"><input v-model.number="payAmt" type="number" step="0.001" dir="ltr" style="width:120px;padding:7px;border:1px solid var(--line);border-radius:8px" /><button class="btn info" @click="act('pay', { amount: payAmt })">تسجيل دفعة</button></template>
        <button v-if="view.status !== 'cancelled'" class="btn ghost" style="color:var(--primary)" @click="confirm('إلغاء الفاتورة؟') && act('cancel')">إلغاء الفاتورة</button>
      </template>
    </UiModal>
  </div>
</template>

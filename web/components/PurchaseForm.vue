<script setup lang="ts">
/** Purchase invoice form: header + item lines + totals. Mutates `form` in place. */
const props = defineProps<{ form: any; suppliers: any[]; warehouses: any[]; isNew?: boolean }>()
const api = useApi(); const f = useFmt()
const iq = ref(''); const found = ref<any[]>([]); let timer: any = null
const totals = computed(() => calcPurchase(props.form))
const supplier = computed(() => props.suppliers.find((s: any) => String(s.id) === String(props.form.supplier_id)))

function onSearch() {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    if (!iq.value.trim()) { found.value = []; return }
    try { const r: any = await api.get('/app/inventory/items/lookup', { q: iq.value, warehouse_id: props.form.warehouse_id || '' }); found.value = r.items || []; if (r.exact && r.items?.length === 1) add(r.items[0]) } catch { found.value = [] }
  }, 250)
}
function add(i: any) {
  const ex = props.form.lines.find((l: any) => l.item_id === i.id)
  ex ? ex.qty++ : props.form.lines.push({ item_id: i.id, name: i.name, sku: i.sku, unit: i.unit?.symbol || '', qty: 1, unit_cost: +i.cost_price || 0, stock: i.stock })
  found.value = []; iq.value = ''
}
// Supplier payment terms → due date when empty.
watch(() => [props.form.supplier_id, props.form.date], () => {
  if (props.form.due_date || !supplier.value?.payment_terms_days || !props.form.date) return
  const d = new Date(props.form.date + 'T00:00:00'); d.setDate(d.getDate() + +supplier.value.payment_terms_days); props.form.due_date = d.toISOString().slice(0, 10)
})
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-truck-delivery" /> بيانات الفاتورة</div>
    <div class="grid grid-4">
      <div class="field"><label>المورد</label><UiSelect v-model="form.supplier_id" placeholder="—"><option value="">—</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></UiSelect><small v-if="supplier" class="muted">{{ [supplier.phone, supplier.payment_terms_days ? `سداد ${supplier.payment_terms_days} يوم` : ''].filter(Boolean).join(' · ') }}</small></div>
      <div class="field"><label>المستودع المستلم</label><UiSelect v-model="form.warehouse_id"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></UiSelect></div>
      <div class="field"><label>تاريخ الفاتورة <span style="color:var(--primary)">*</span></label><input v-model="form.date" type="date" dir="ltr" /></div>
      <div class="field"><label>تاريخ الاستحقاق</label><input v-model="form.due_date" type="date" dir="ltr" /></div>
      <div class="field"><label>رقم فاتورة المورد</label><input v-model="form.supplier_invoice" dir="ltr" /></div>
      <div class="field"><label>مرجع / أمر شراء</label><input v-model="form.reference" dir="ltr" /></div>
      <div class="field"><label>طريقة الدفع</label><UiSelect v-model="form.payment_method"><option v-for="(l, k) in purchasePayMethods" :key="k" :value="k">{{ l }}</option></UiSelect></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-list-details" /> الأصناف</div>
    <div class="field" style="position:relative">
      <label>أضف صنفًا (اسم / SKU / باركود)</label>
      <input v-model="iq" placeholder="اكتب للبحث… الباركود المطابق يُضاف مباشرة" @input="onSearch" @keydown.esc="found = []" />
      <div v-if="found.length" class="line-search" style="position:absolute;z-index:30;left:0;right:0;top:100%">
        <div class="line-results">
          <button v-for="i in found" :key="i.id" type="button" @click="add(i)"><b>{{ i.name }}</b><small class="muted"> {{ i.sku || '' }} · تكلفة {{ f.money(i.cost_price) }}<span v-if="i.stock != null"> · متوفر {{ f.qty(i.stock) }}</span></small></button>
        </div>
      </div>
    </div>
    <div class="lines-editor">
      <table class="table">
        <thead><tr><th style="width:36px">#</th><th>الصنف</th><th style="width:110px">الكمية</th><th style="width:70px">الوحدة</th><th style="width:130px">تكلفة الوحدة</th><th style="width:130px">الإجمالي</th><th style="width:48px"></th></tr></thead>
        <tbody>
          <tr v-for="(l, i) in form.lines" :key="l.item_id">
            <td class="muted">{{ i + 1 }}</td>
            <td><b>{{ l.name }}</b><small v-if="l.sku" class="muted" dir="ltr"> ({{ l.sku }})</small><small v-if="l.stock != null" class="muted"> · الرصيد الحالي {{ f.qty(l.stock) }}</small></td>
            <td><input v-model.number="l.qty" type="number" step="0.001" min="0" dir="ltr" /></td>
            <td>{{ l.unit || '—' }}</td>
            <td><input v-model.number="l.unit_cost" type="number" step="0.001" min="0" dir="ltr" /></td>
            <td class="num"><b>{{ f.money((+l.qty || 0) * (+l.unit_cost || 0)) }}</b></td>
            <td><button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="form.lines.splice(i, 1)"><i class="ti ti-x" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!form.lines.length" class="empty" style="padding:18px 0">لم تُضف أصنافًا بعد.</p>
    </div>

    <div class="grid grid-2" style="margin-top:16px;align-items:start">
      <div>
        <div class="card-title"><i class="ti ti-discount-2" /> الخصم والضريبة والدفع</div>
        <div class="grid grid-3">
          <div class="field"><label>خصم (مبلغ)</label><input v-model.number="form.discount" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>ضريبة (مبلغ)</label><input v-model.number="form.tax" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>المدفوع الآن</label><input v-model.number="form.paid" type="number" step="0.001" min="0" dir="ltr" /></div>
        </div>
        <div class="field"><label>ملاحظات</label><textarea v-model="form.note" rows="2" /></div>
        <label v-if="isNew" class="switch"><input v-model="form.receive" type="checkbox" /><span /> استلام فوري (تدخل الكميات المخزون عند الحفظ)</label>
      </div>
      <div class="totals-box">
        <div class="row between"><span>المجموع</span><b>{{ f.money(totals.subtotal) }}</b></div>
        <div class="row between"><span>الخصم</span><b>- {{ f.money(form.discount) }}</b></div>
        <div class="row between"><span>الضريبة</span><b>{{ f.money(form.tax) }}</b></div>
        <div class="row between total"><span>الإجمالي</span><b>{{ f.money(totals.total, true) }}</b></div>
        <div class="row between"><span>المدفوع</span><b>{{ f.money(form.paid) }}</b></div>
        <div class="row between"><span>المتبقي للمورد</span><b :style="totals.balance > 0 ? 'color:var(--primary)' : ''">{{ f.money(totals.balance) }}</b></div>
      </div>
    </div>
  </div>
</template>

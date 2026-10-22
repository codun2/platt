<script setup lang="ts">
/** Purchase invoice form: header + item lines + totals. Mutates `form` in place. */
const props = defineProps<{ form: any; suppliers: any[]; warehouses: any[]; isNew?: boolean }>()
const api = useApi(); const f = useFmt(); const { tt } = useI18n()
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
    <div class="card-title"><i class="ti ti-truck-delivery" /> {{ tt('بيانات الفاتورة','Invoice details') }}</div>
    <div class="grid grid-4">
      <div class="field"><label>{{ tt('المورد','Supplier') }}</label><UiSelect v-model="form.supplier_id" placeholder="—"><option value="">—</option><option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option></UiSelect><small v-if="supplier" class="muted">{{ [supplier.phone, supplier.payment_terms_days ? tt(`سداد ${supplier.payment_terms_days} يوم`,`${supplier.payment_terms_days}d terms`) : ''].filter(Boolean).join(' · ') }}</small></div>
      <div class="field"><label>{{ tt('المستودع المستلم','Receiving warehouse') }}</label><UiSelect v-model="form.warehouse_id"><option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('تاريخ الفاتورة','Invoice date') }} <span style="color:var(--primary)">*</span></label><input v-model="form.date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('تاريخ الاستحقاق','Due date') }}</label><input v-model="form.due_date" type="date" dir="ltr" /></div>
      <div class="field"><label>{{ tt('رقم فاتورة المورد','Supplier invoice no.') }}</label><input v-model="form.supplier_invoice" dir="ltr" /></div>
      <div class="field"><label>{{ tt('مرجع / أمر شراء','Reference / PO') }}</label><input v-model="form.reference" dir="ltr" /></div>
      <div class="field"><label>{{ tt('طريقة الدفع','Payment method') }}</label><UiSelect v-model="form.payment_method"><option v-for="(l, k) in purchasePayMethods" :key="k" :value="k">{{ l }}</option></UiSelect></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-list-details" /> {{ tt('الأصناف','Items') }}</div>
    <div class="field" style="position:relative">
      <label>{{ tt('أضف صنفًا (اسم / SKU / باركود)','Add item (name / SKU / barcode)') }}</label>
      <input v-model="iq" :placeholder="tt('اكتب للبحث… الباركود المطابق يُضاف مباشرة','Type to search… exact barcode adds instantly')" @input="onSearch" @keydown.esc="found = []" />
      <div v-if="found.length" class="line-search" style="position:absolute;z-index:30;left:0;right:0;top:100%">
        <div class="line-results">
          <button v-for="i in found" :key="i.id" type="button" @click="add(i)"><b>{{ i.name }}</b><small class="muted"> {{ i.sku || '' }} · {{ tt('تكلفة','cost') }} {{ f.money(i.cost_price) }}<span v-if="i.stock != null"> · {{ tt('متوفر','in stock') }} {{ f.qty(i.stock) }}</span></small></button>
        </div>
      </div>
    </div>
    <div class="lines-editor">
      <table class="table">
        <thead><tr><th style="width:36px">#</th><th>{{ tt('الصنف','Item') }}</th><th style="width:110px">{{ tt('الكمية','Qty') }}</th><th style="width:70px">{{ tt('الوحدة','Unit') }}</th><th style="width:130px">{{ tt('تكلفة الوحدة','Unit cost') }}</th><th style="width:130px">{{ tt('الإجمالي','Total') }}</th><th style="width:48px"></th></tr></thead>
        <tbody>
          <tr v-for="(l, i) in form.lines" :key="l.item_id">
            <td class="muted">{{ i + 1 }}</td>
            <td><b>{{ l.name }}</b><small v-if="l.sku" class="muted" dir="ltr"> ({{ l.sku }})</small><small v-if="l.stock != null" class="muted"> · {{ tt('الرصيد الحالي','current') }} {{ f.qty(l.stock) }}</small></td>
            <td><input v-model.number="l.qty" type="number" step="0.001" min="0" dir="ltr" /></td>
            <td>{{ l.unit || '—' }}</td>
            <td><input v-model.number="l.unit_cost" type="number" step="0.001" min="0" dir="ltr" /></td>
            <td class="num"><b>{{ f.money((+l.qty || 0) * (+l.unit_cost || 0)) }}</b></td>
            <td><button type="button" class="btn ghost sm icon" style="color:var(--primary)" @click="form.lines.splice(i, 1)"><i class="ti ti-x" /></button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!form.lines.length" class="empty" style="padding:18px 0">{{ tt('لم تُضف أصنافًا بعد.','No items added yet.') }}</p>
    </div>

    <div class="grid grid-2" style="margin-top:16px;align-items:start">
      <div>
        <div class="card-title"><i class="ti ti-discount-2" /> {{ tt('الخصم والضريبة والدفع','Discount, tax & payment') }}</div>
        <div class="grid grid-3">
          <div class="field"><label>{{ tt('خصم (مبلغ)','Discount (amount)') }}</label><input v-model.number="form.discount" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>{{ tt('ضريبة (مبلغ)','Tax (amount)') }}</label><input v-model.number="form.tax" type="number" step="0.001" min="0" dir="ltr" /></div>
          <div class="field"><label>{{ tt('المدفوع الآن','Paid now') }}</label><input v-model.number="form.paid" type="number" step="0.001" min="0" dir="ltr" /></div>
        </div>
        <div class="field"><label>{{ tt('ملاحظات','Notes') }}</label><textarea v-model="form.note" rows="2" /></div>
        <label v-if="isNew" class="switch"><input v-model="form.receive" type="checkbox" /><span /> {{ tt('استلام فوري (تدخل الكميات المخزون عند الحفظ)','Receive now (stock in on save)') }}</label>
      </div>
      <div class="totals-box">
        <div class="row between"><span>{{ tt('المجموع','Subtotal') }}</span><b>{{ f.money(totals.subtotal) }}</b></div>
        <div class="row between"><span>{{ tt('الخصم','Discount') }}</span><b>- {{ f.money(form.discount) }}</b></div>
        <div class="row between"><span>{{ tt('الضريبة','Tax') }}</span><b>{{ f.money(form.tax) }}</b></div>
        <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(totals.total, true) }}</b></div>
        <div class="row between"><span>{{ tt('المدفوع','Paid') }}</span><b>{{ f.money(form.paid) }}</b></div>
        <div class="row between"><span>{{ tt('المتبقي للمورد','Balance to supplier') }}</span><b :style="totals.balance > 0 ? 'color:var(--primary)' : ''">{{ f.money(totals.balance) }}</b></div>
      </div>
    </div>
  </div>
</template>

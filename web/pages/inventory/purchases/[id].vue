<script setup lang="ts">
definePageMeta({ title: 'فاتورة الشراء', titleEn: 'Purchase Invoice' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const { tt } = useI18n()
const route = useRoute(); const id = Number(route.params.id)
const p = ref<any>(null); const suppliers = ref<any[]>([]); const meta = ref<any>({ warehouses: [] })
const editing = ref(route.query.edit === '1'); const paying = ref(false)
const err = ref(''); const msg = ref(''); const saving = ref(false); const notFound = ref(false)
const form = reactive<any>(blankPurchase()); const payAmt = ref(0)
const canManage = computed(() => auth.can('inventory.manage'))
const balance = computed(() => Math.max(0, +p.value.total - +p.value.paid))
const dueDays = computed(() => daysUntil(p.value?.due_date))

async function load() {
  try { p.value = await api.get(`/app/inventory/purchases/${id}`) } catch (e: any) { if (e.status === 404) { notFound.value = true; return } throw e }
  Object.assign(form, purchaseToForm(p.value)); payAmt.value = balance.value
}
onMounted(async () => { await load(); suppliers.value = await api.get('/app/inventory/suppliers'); meta.value = await api.get('/app/inventory/items/meta') })
async function act(fn: () => Promise<any>, ok = tt('تم','Done')) {
  err.value = ''; msg.value = ''; saving.value = true
  try { await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
const save = () => act(async () => { await api.patch(`/app/inventory/purchases/${id}`, purchasePayload(form)); editing.value = false }, tt('تم حفظ التعديلات','Changes saved'))
const receive = () => act(() => api.post(`/app/inventory/purchases/${id}/receive`), tt('تم استلام الأصناف إلى المخزون','Items received into stock'))
const cancel = () => { if (confirm(p.value.status === 'received' ? tt('إلغاء الفاتورة وسحب الكميات من المخزون؟','Cancel and pull quantities from stock?') : tt('إلغاء الفاتورة؟','Cancel invoice?'))) act(() => api.post(`/app/inventory/purchases/${id}/cancel`), tt('تم إلغاء الفاتورة','Invoice cancelled')) }
const doPay = () => act(async () => { await api.post(`/app/inventory/purchases/${id}/pay`, { amount: payAmt.value }); paying.value = false }, tt('تم تسجيل الدفعة','Payment recorded'))
</script>

<template>
  <div v-if="p">
    <div class="breadcrumb"><NuxtLink to="/inventory/purchases">{{ tt('المشتريات','Purchases') }}</NuxtLink> › <b dir="ltr">{{ p.number }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <PurchaseForm :form="form" :suppliers="suppliers" :warehouses="meta.warehouses" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" :disabled="saving || !form.lines.length" @click="save"><i class="ti ti-check" /> {{ tt('حفظ التعديلات','Save changes') }}</button>
        <button class="btn ghost" :disabled="saving" @click="editing = false; load()">{{ tt('إلغاء','Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <div class="grid grid-4" style="margin-bottom:16px">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-truck-delivery" /></div><div><b>{{ f.money(p.total, true) }}</b><small>{{ tt('إجمالي الفاتورة','Invoice total') }} · {{ p.items.length }} {{ tt('بند','lines') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(p.paid) }}</b><small>{{ tt('المدفوع','Paid') }} ({{ purchasePayMethods[p.payment_method] || p.payment_method }})</small></div></div>
        <div class="card stat"><div class="ic" :style="`background:${balance > 0 ? 'var(--primary)' : 'var(--ok)'}`"><i class="ti ti-wallet" /></div><div><b>{{ f.money(balance) }}</b><small>{{ tt('المتبقي للمورد','Balance to supplier') }}</small></div></div>
        <div class="card stat"><div class="ic" :style="`background:${dueDays !== null && dueDays < 0 && balance > 0 ? 'var(--primary)' : 'var(--warn)'}`"><i class="ti ti-calendar-due" /></div><div><b>{{ f.d(p.due_date) }}</b><small v-if="dueDays !== null && balance > 0 && p.status !== 'cancelled'">{{ dueDays < 0 ? tt(`متأخر ${-dueDays} يوم`,`${-dueDays}d overdue`) : dueDays === 0 ? tt('يستحق اليوم','Due today') : tt(`بعد ${dueDays} يوم`,`in ${dueDays}d`) }}</small><small v-else>{{ tt('تاريخ الاستحقاق','Due date') }}</small></div></div>
      </div>

      <div class="card">
        <div class="row between" style="align-items:flex-start">
          <div>
            <div class="row" style="gap:10px;align-items:center"><h2 style="margin:0" dir="ltr">{{ p.number }}</h2><span class="badge" :class="purchaseStatus[p.status]?.cls">{{ purchaseStatus[p.status]?.label }}</span></div>
            <p class="muted" style="margin:6px 0 0">{{ f.d(p.date) }} · {{ p.warehouse?.name }}<span v-if="p.creator"> · {{ tt('أنشأها','by') }} {{ p.creator.name }}</span><span v-if="p.received_at"> · استُلمت {{ f.dt(p.received_at) }}</span></p>
          </div>
          <div v-if="canManage" class="row" style="gap:8px;flex-wrap:wrap;justify-content:flex-end">
            <button v-if="p.status === 'draft'" class="btn ok sm" @click="receive"><i class="ti ti-package-import" /> {{ tt('استلام للمخزون','Receive to stock') }}</button>
            <button v-if="p.status !== 'cancelled' && balance > 0" class="btn info sm" @click="paying = !paying"><i class="ti ti-cash" /> {{ tt('تسجيل دفعة','Add payment') }}</button>
            <button v-if="p.status === 'draft'" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> {{ tt('تعديل','Edit') }}</button>
            <button v-if="p.status !== 'cancelled'" class="btn ghost sm" style="color:var(--primary)" @click="cancel"><i class="ti ti-ban" /> {{ tt('إلغاء الفاتورة','Cancel invoice') }}</button>
          </div>
        </div>
        <div v-if="paying" class="pay-panel mt">
          <div class="card-title" style="margin-bottom:8px"><i class="ti ti-cash" /> {{ tt('تسجيل دفعة للمورد','Pay supplier') }} — {{ tt('المتبقي','balance') }} {{ f.money(balance, true) }}</div>
          <div class="row" style="align-items:flex-end;gap:10px">
            <div class="field" style="margin:0"><label>{{ tt('المبلغ','Amount') }}</label><input v-model.number="payAmt" type="number" step="0.001" min="0" :max="balance" dir="ltr" style="width:140px" /></div>
            <button class="btn" :disabled="saving || !payAmt" @click="doPay"><i class="ti ti-check" /> {{ tt('حفظ الدفعة','Save payment') }}</button>
            <button class="btn ghost" @click="paying = false">{{ tt('إغلاق','Close') }}</button>
          </div>
        </div>
        <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
        <div class="grid grid-3">
          <div><small class="muted">{{ tt('المورد','Supplier') }}</small><br /><b v-if="p.supplier"><NuxtLink :to="`/inventory/suppliers/${p.supplier.id}`" style="color:var(--info)">{{ p.supplier.name }}</NuxtLink></b><b v-else>—</b><div v-if="p.supplier" class="muted" style="font-size:12px">{{ [p.supplier.phone, p.supplier.email].filter(Boolean).join(' · ') }}</div></div>
          <div><small class="muted">{{ tt('رقم فاتورة المورد','Supplier invoice no.') }}</small><br /><b dir="ltr">{{ p.supplier_invoice || '—' }}</b><br /><small class="muted">{{ tt('المرجع / أمر الشراء','Reference / PO') }}</small><br /><b dir="ltr">{{ p.reference || '—' }}</b></div>
          <div><small class="muted">{{ tt('طريقة الدفع','Payment method') }}</small><br /><b>{{ purchasePayMethods[p.payment_method] || p.payment_method }}</b><br /><small class="muted">{{ tt('ملاحظات','Notes') }}</small><br /><b>{{ p.note || '—' }}</b></div>
        </div>
      </div>

      <div class="card">
        <div class="card-title"><i class="ti ti-list-details" /> {{ tt('الأصناف','Items') }} ({{ p.items.length }})</div>
        <table class="table"><thead><tr><th>#</th><th>{{ tt('الصنف','Item') }}</th><th>{{ tt('الكمية','Qty') }}</th><th>{{ tt('الوحدة','Unit') }}</th><th>{{ tt('تكلفة الوحدة','Unit cost') }}</th><th>{{ tt('الإجمالي','Total') }}</th></tr></thead>
          <tbody><tr v-for="(l, i) in p.items" :key="l.id"><td class="muted">{{ i + 1 }}</td><td><NuxtLink :to="`/inventory/items/${l.item_id}`" style="color:var(--info)">{{ l.item?.name }}</NuxtLink><small v-if="l.item?.sku" class="muted" dir="ltr"> ({{ l.item.sku }})</small></td><td class="num">{{ f.qty(l.qty) }}</td><td>{{ l.item?.unit?.symbol || '—' }}</td><td class="num">{{ f.money(l.unit_cost) }}</td><td class="num"><b>{{ f.money(l.total) }}</b></td></tr></tbody></table>
        <div class="totals-box" style="margin-right:auto;max-width:340px">
          <div class="row between"><span>{{ tt('المجموع','Subtotal') }}</span><b>{{ f.money(p.subtotal) }}</b></div>
          <div class="row between"><span>{{ tt('الخصم','Discount') }}</span><b>- {{ f.money(p.discount) }}</b></div>
          <div class="row between"><span>{{ tt('الضريبة','Tax') }}</span><b>{{ f.money(p.tax) }}</b></div>
          <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(p.total, true) }}</b></div>
          <div class="row between"><span>{{ tt('المدفوع','Paid') }}</span><b>{{ f.money(p.paid) }}</b></div>
          <div class="row between"><span>{{ tt('المتبقي','Balance') }}</span><b :style="balance > 0 ? 'color:var(--primary)' : ''">{{ f.money(balance) }}</b></div>
        </div>
      </div>
    </template>
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px"><i class="ti ti-file-off" style="font-size:48px;color:var(--muted)" /><h2 class="mt">{{ tt('فاتورة الشراء غير موجودة','Purchase not found') }}</h2><NuxtLink to="/inventory/purchases" class="btn ghost mt">{{ tt('العودة إلى المشتريات','Back to purchases') }}</NuxtLink></div>
</template>

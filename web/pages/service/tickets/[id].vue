<script setup lang="ts">
definePageMeta({ title: 'أمر العمل', titleEn: 'Work Order' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute(); const S = useServiceSector()
const id = Number(route.params.id)
const o = ref<any>(null); const err = ref(''); const msg = ref(''); const busy = ref(false)
const editing = ref(false); const form = reactive<any>(blankOrder())
const customers = ref<any[]>([]); const technicians = ref<any[]>([]); const catalog = ref<any[]>([])
const pay = reactive({ paid: 0, method: 'cash' })
const tax = computed(() => +(auth.state.value.tenant?.tax_rate || 0))
const company = computed(() => auth.state.value.tenant)

async function load() {
  o.value = await api.get(`/app/service/orders/${id}`)
  pay.paid = +(orderTotal(orderToForm(o.value)) * (1 + tax.value / 100)).toFixed(3)
}
onMounted(async () => { await load(); if (route.query.print === '1') { await nextTick(); setTimeout(() => window.print(), 400) } })
const isOpen = computed(() => o.value && OPEN_ORDER.includes(o.value.status))
const who = computed(() => o.value?.customer?.name || o.value?.customer_name)
const phone = computed(() => o.value?.customer?.phone || o.value?.customer_phone)
const total = computed(() => o.value ? orderTotal(orderToForm(o.value)) : 0)
async function setStatus(s: string) {
  if (s === 'cancelled' && !(await uiConfirm(tt('إلغاء هذا الطلب؟', 'Cancel this order?')))) return
  busy.value = true; err.value = ''
  try { o.value = await api.post(`/app/service/orders/${id}/status`, { status: s }); msg.value = tt('تم تحديث الحالة', 'Status updated') } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
async function deliver() {
  if (!(await uiConfirm(o.value.lines.length ? tt(`تسليم الطلب وإصدار الفاتورة بقيمة ${f.money(total.value * (1 + tax.value / 100))}؟`, `Deliver and issue the invoice?`) : tt('تسليم الطلب بدون فاتورة (لا توجد بنود)؟', 'Deliver without an invoice (no lines)?'), { tone: 'info', okText: tt('تسليم', 'Deliver') }))) return
  busy.value = true; err.value = ''
  try { o.value = await api.post(`/app/service/orders/${id}/deliver`, pay); msg.value = tt('تم التسليم وإصدار الفاتورة', 'Delivered and invoiced') } catch (e: any) { err.value = e.message } finally { busy.value = false }
}
async function startEdit() {
  Object.assign(form, orderToForm(o.value))
  ;[customers.value, technicians.value, catalog.value] = await Promise.all([api.get('/app/customers', { all: 1, active_only: 1 }), api.get('/app/service/technicians', { active_only: 1 }), api.get('/app/service/catalog', { active_only: 1 })])
  editing.value = true
}
async function saveEdit() { err.value = ''; try { await api.patch(`/app/service/orders/${id}`, orderPayload(form)); editing.value = false; msg.value = tt('تم الحفظ', 'Saved'); await load() } catch (e: any) { err.value = e.message } }
async function remove() { if (!(await uiConfirm(tt('حذف هذا الطلب نهائياً؟', 'Delete this order permanently?')))) return; try { await api.del(`/app/service/orders/${id}`); navigateTo('/service/tickets') } catch (e: any) { uiAlert(e.message) } }
const printReceipt = () => window.print()
usePageTitle(() => o.value ? `${S.order} ${o.value.number}` : null)
const steps = ['received', 'in_progress', 'waiting_parts', 'ready']
const details = computed(() => S.spec.fields.map(fd => [fieldLabel(fd), fd.type === 'switch' ? (o.value?.details?.[fd.key] ? tt('نعم', 'Yes') : '') : fd.type === 'select' ? optionLabel(fd, o.value?.details?.[fd.key] || '') : o.value?.details?.[fd.key]]).filter(r => r[1] !== '' && r[1] !== null && r[1] !== undefined))
</script>

<template>
  <div v-if="o">
    <div class="breadcrumb no-print"><NuxtLink to="/service/tickets">{{ S.orders }}</NuxtLink> › <b dir="ltr">{{ o.number }}</b></div>
    <div v-if="msg" class="alert ok no-print">{{ msg }}</div>
    <div v-if="err" class="alert bad no-print">{{ err }}</div>

    <div v-if="editing" class="card no-print">
      <ServiceOrderForm :form="form" :customers="customers" :technicians="technicians" :catalog="catalog" />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" @click="saveEdit"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button>
        <button class="btn ghost" @click="editing = false">{{ tt('إلغاء', 'Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <div class="card no-print">
        <div class="row between" style="align-items:flex-start">
          <div>
            <div class="row" style="gap:10px;align-items:center"><h2 style="margin:0" dir="ltr">{{ o.number }}</h2><span class="badge" :class="orderStatus[o.status]?.cls">{{ orderStatus[o.status]?.label }}</span><span v-if="o.priority !== 'normal'" class="badge" :class="priorities[o.priority]?.cls">{{ priorities[o.priority]?.label }}</span></div>
            <p class="muted" style="margin:6px 0 0"><b style="color:var(--ink)">{{ who }}</b> <a v-if="phone" :href="`https://wa.me/${String(phone).replace(/\D/g, '')}`" target="_blank" style="color:var(--ok)" dir="ltr"><i class="ti ti-brand-whatsapp" /> {{ phone }}</a>
              · {{ tt('استلام', 'In') }} <span dir="ltr">{{ o.received_at }}</span><template v-if="o.promised_at"> · {{ tt('التسليم', 'Due') }} <span dir="ltr">{{ o.promised_at }}</span></template>
              <template v-if="o.technician"> · {{ S.tech }}: {{ o.technician.name }}</template></p>
          </div>
          <div class="row" style="gap:8px">
            <button class="btn ghost sm" @click="printReceipt"><i class="ti ti-printer" /> {{ tt('إيصال', 'Receipt') }}</button>
            <template v-if="isOpen"><button class="btn ghost sm" @click="startEdit"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</button><button class="btn ghost sm" style="color:var(--primary)" @click="setStatus('cancelled')"><i class="ti ti-ban" /></button></template>
            <button v-if="o.status !== 'delivered' && auth.can('service.manage')" class="btn ghost sm icon" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button>
          </div>
        </div>
        <div v-if="isOpen" class="stepper mt">
          <button v-for="s in steps" :key="s" class="step" :class="{ on: o.status === s }" :disabled="busy" @click="setStatus(s)"><i :class="`ti ${orderStatus[s].icon}`" /> {{ orderStatus[s].label }}</button>
        </div>
        <div v-if="o.invoice" class="alert ok mt row between" style="margin-bottom:0"><span>{{ tt('الفاتورة', 'Invoice') }} <b dir="ltr">{{ o.invoice.number }}</b> · {{ f.money(o.invoice.total, true) }} · {{ tt('مدفوع', 'paid') }} {{ f.money(o.invoice.paid) }}</span><NuxtLink :to="`/crm/invoices/${o.invoice.id}`" class="btn ghost sm">{{ tt('فتح الفاتورة', 'Open invoice') }}</NuxtLink></div>
      </div>

      <div class="grid grid-2 mt no-print" style="align-items:start">
        <div class="card">
          <div class="card-title"><i :class="`ti ${S.spec.icon}`" /> {{ tt(S.spec.title[0], S.spec.title[1]) }}</div>
          <div class="grid grid-2"><div v-for="[k, v] in details" :key="k"><small class="muted">{{ k }}</small><br /><b>{{ v }}</b></div></div>
          <div class="mt"><small class="muted">{{ S.titleLabel }}</small><p style="margin:4px 0 0;white-space:pre-line">{{ o.title }}</p></div>
          <div v-if="o.diagnosis" class="mt"><small class="muted">{{ tt('التشخيص', 'Diagnosis') }}</small><p style="margin:4px 0 0;white-space:pre-line">{{ o.diagnosis }}</p></div>
          <div v-if="o.internal_notes" style="background:var(--bg);padding:10px;border-radius:10px;margin-top:10px"><small class="muted"><i class="ti ti-lock" /> {{ tt('ملاحظات داخلية', 'Internal') }}</small><p style="margin:4px 0 0;white-space:pre-line">{{ o.internal_notes }}</p></div>
        </div>
        <div>
          <div class="card">
            <div class="card-title"><i class="ti ti-list-details" /> {{ tt('الخدمات والقطع', 'Services & parts') }}</div>
            <table v-if="o.lines.length" class="table"><tbody><tr v-for="l in o.lines" :key="l.id"><td>{{ l.description }} <span class="badge" :class="l.kind === 'part' ? 'info' : 'muted'" style="font-size:10px">{{ l.kind === 'part' ? tt('قطعة', 'Part') : tt('خدمة', 'Labor') }}</span></td><td class="num">{{ f.qty(l.qty) }} × {{ f.money(l.unit_price) }}</td><td class="num"><b>{{ f.money(l.total) }}</b></td></tr></tbody></table>
            <p v-else class="empty">{{ tt('لم تُضف بنود بعد — اضغط «تعديل» لإضافتها.', 'No lines yet — use Edit.') }}</p>
            <div class="totals-box" style="max-width:none">
              <div v-if="+o.discount" class="row between"><span>{{ tt('خصم', 'Discount') }}</span><b>- {{ f.money(o.discount) }}</b></div>
              <div class="row between"><span>{{ tt('الضريبة', 'Tax') }} ({{ tax }}%)</span><b>{{ f.money(total * tax / 100) }}</b></div>
              <div class="row between total"><span>{{ tt('الإجمالي', 'Total') }}</span><b>{{ f.money(total * (1 + tax / 100), true) }}</b></div>
              <div v-if="+o.estimate" class="row between muted"><span>{{ tt('التقدير المُعطى للعميل', 'Estimate given') }}</span><span>{{ f.money(o.estimate) }}</span></div>
            </div>
          </div>
          <div v-if="isOpen" class="card">
            <div class="card-title"><i class="ti ti-truck-delivery" /> {{ tt('التسليم للعميل', 'Hand over') }}</div>
            <div class="grid grid-2">
              <div class="field"><label>{{ tt('المبلغ المدفوع الآن', 'Paid now') }}</label><input v-model.number="pay.paid" type="number" min="0" step="0.001" dir="ltr" /><small class="muted">{{ tt('0 = آجل على حساب العميل', '0 = on account') }}</small></div>
              <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="pay.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option></UiSelect></div>
            </div>
            <button class="btn ok" :disabled="busy" @click="deliver"><i class="ti ti-check" /> {{ tt('تسليم وإصدار الفاتورة', 'Deliver & invoice') }}</button>
            <small class="muted" style="display:block;margin-top:6px">{{ tt('تُخصم القطع من المخزون وتُرحَّل الفاتورة وتكلفتها للمحاسبة.', 'Parts leave stock; the invoice and its cost post to accounting.') }}</small>
          </div>
        </div>
      </div>

      <!-- Printed intake / job receipt -->
      <div class="print-only receipt">
        <h2 style="margin:0">{{ company?.name }}</h2>
        <small>{{ company?.phone }}</small>
        <hr />
        <div class="row between"><b>{{ S.order }}</b><b dir="ltr">{{ o.number }}</b></div>
        <div>{{ tt('العميل', 'Customer') }}: {{ who }} <span dir="ltr">{{ phone }}</span></div>
        <div>{{ tt('الاستلام', 'Received') }}: <span dir="ltr">{{ o.received_at }}</span></div>
        <div v-if="o.promised_at">{{ tt('التسليم المتوقع', 'Ready by') }}: <span dir="ltr">{{ o.promised_at }}</span></div>
        <hr />
        <div v-for="[k, v] in details" :key="k">{{ k }}: <b>{{ v }}</b></div>
        <p>{{ S.titleLabel }}: {{ o.title }}</p>
        <table v-if="o.lines.length" style="width:100%"><tr v-for="l in o.lines" :key="l.id"><td>{{ l.description }}</td><td style="text-align:left">{{ f.qty(l.qty) }} × {{ f.money(l.unit_price) }}</td></tr></table>
        <hr />
        <div class="row between"><b>{{ tt('الإجمالي التقريبي', 'Estimated total') }}</b><b>{{ f.money((total || +o.estimate) * (1 + tax / 100), true) }}</b></div>
        <p style="font-size:11px;margin-top:10px">{{ tt('يرجى إحضار هذا الإيصال عند الاستلام.', 'Please bring this receipt at pickup.') }}</p>
      </div>
    </template>
  </div>
</template>

<style scoped>
.stepper { display: flex; gap: 6px; flex-wrap: wrap; }
.step { flex: 1; min-width: 120px; padding: 9px 10px; border: 1px solid var(--line); background: var(--card); border-radius: 10px; cursor: pointer; font: inherit; color: var(--muted); }
.step.on { background: var(--primary); color: #fff; border-color: var(--primary); font-weight: 700; }
.step:not(.on):hover { border-color: var(--primary); color: var(--primary); }
.print-only { display: none; }
@media print {
  .print-only { display: block; }
  .receipt { max-width: 80mm; margin: 0 auto; font-size: 12px; }
  .receipt hr { border: 0; border-top: 1px dashed #999; margin: 6px 0; }
}
</style>

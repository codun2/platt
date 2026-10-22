<script setup lang="ts">
definePageMeta({ title: 'عرض السعر', titleEn: 'Quote' })
const api = useApi(); const auth = useAuth(); const f = useFmt()
const route = useRoute(); const id = Number(route.params.id)

const data = ref<any>(null); const customers = ref<any[]>([])
const editing = ref(route.query.edit === '1'); const converting = ref(false)
const err = ref(''); const msg = ref(''); const saving = ref(false)
const form = reactive<any>(blankDoc('quote'))
const conv = reactive({ date: f.today(), payment_terms_days: '', status: 'sent' })
const q = computed(() => data.value?.quote)
const canManage = computed(() => auth.can('crm.manage'))
const validDays = computed(() => daysUntil(q.value?.valid_until))
const editable = computed(() => q.value && ['draft', 'sent'].includes(q.value.status))
const print = () => window.print()
const exporting = ref(false)
async function exportPdf() {
  exporting.value = true
  try { await exportDocPdf(document.querySelector('.doc-print') as HTMLElement, docPdfName(q.value.number, data.value.company?.name)) }
  catch (e: any) { err.value = e.message } finally { exporting.value = false }
}

const notFound = ref(false)
async function load() {
  try { data.value = await api.get(`/app/quotes/${id}`) } catch (e: any) { if (e.status === 404) { notFound.value = true; return } throw e }
  Object.assign(form, docToForm('quote', data.value.quote))
}
onMounted(async () => { await load(); customers.value = await api.get('/app/customers', { all: 1 }); if (route.query.print) { await nextTick(); setTimeout(() => window.print(), 400) } })

async function act(fn: () => Promise<any>, ok = 'تم') {
  err.value = ''; msg.value = ''; saving.value = true
  try { await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message } finally { saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
const save = () => act(async () => { await api.patch(`/app/quotes/${id}`, docPayload(form)); editing.value = false }, 'تم حفظ التعديلات')
const setStatus = (status: string, label: string) => act(() => api.post(`/app/quotes/${id}/status`, { status }), label)
async function convert() {
  err.value = ''; saving.value = true
  try { const r = await api.post(`/app/quotes/${id}/convert`, { ...conv, payment_terms_days: conv.payment_terms_days === '' ? null : +conv.payment_terms_days }); navigateTo(`/crm/invoices/${r.invoice.id}`) }
  catch (e: any) { err.value = e.message; saving.value = false; window.scrollTo({ top: 0, behavior: 'smooth' }) }
}
async function remove() { if (!confirm('حذف عرض السعر نهائياً؟')) return; try { await api.del(`/app/quotes/${id}`); navigateTo('/crm/quotes') } catch (e: any) { err.value = e.message } }
</script>

<template>
  <div v-if="q">
    <div class="no-print">
      <div class="breadcrumb"><NuxtLink to="/crm/quotes">عروض الأسعار</NuxtLink> › <b dir="ltr">{{ q.number }}</b></div>
      <div v-if="msg" class="alert ok">{{ msg }}</div>
      <div v-if="err" class="alert bad">{{ err }}</div>

      <div v-if="editing" class="card">
        <DocForm kind="quote" :form="form" :customers="customers" />
        <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
          <button class="btn" :disabled="saving" @click="save"><i class="ti ti-check" /> حفظ التعديلات</button>
          <button class="btn ghost" :disabled="saving" @click="editing = false; load()">إلغاء</button>
        </div>
      </div>

      <template v-else>
        <div class="grid grid-4" style="margin-bottom:16px">
          <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-dollar" /></div><div><b style="font-size:17px">{{ f.money(q.total, true) }}</b><small>قيمة العرض</small></div></div>
          <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-list-details" /></div><div><b>{{ q.items.length }}</b><small>بند</small></div></div>
          <div class="card stat"><div class="ic" :style="`background:${q.is_expired ? 'var(--primary)' : 'var(--warn)'}`"><i class="ti ti-calendar-due" /></div><div><b style="font-size:15px">{{ f.d(q.valid_until) }}</b><small v-if="validDays !== null && ['draft','sent'].includes(q.status)">{{ validDays < 0 ? `انتهت منذ ${-validDays} يوم` : validDays === 0 ? 'ينتهي اليوم' : `متبقٍ ${validDays} يوم` }}</small><small v-else>صالح حتى</small></div></div>
          <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-file-invoice" /></div><div><b v-if="q.invoice" style="font-size:15px"><NuxtLink :to="`/crm/invoices/${q.invoice.id}`" style="color:#fff0;color:var(--ink)" dir="ltr">{{ q.invoice.number }}</NuxtLink></b><b v-else style="font-size:15px">—</b><small>الفاتورة الناتجة</small></div></div>
        </div>

        <div class="card">
          <div class="row between" style="align-items:flex-start">
            <div>
              <div class="row" style="gap:10px;align-items:center">
                <h2 style="margin:0" dir="ltr">{{ q.number }}</h2>
                <span class="badge" :class="quoteStatus[q.status]?.cls">{{ quoteStatus[q.status]?.label }}</span>
                <span v-if="q.is_expired" class="badge warn">تجاوز الصلاحية</span>
              </div>
              <p class="muted" style="margin:6px 0 0">{{ q.subject || 'عرض سعر' }} · {{ f.d(q.date) }}<span v-if="q.creator"> · أعدّه {{ q.creator.name }}</span></p>
            </div>
            <div class="row" style="gap:8px;flex-wrap:wrap;justify-content:flex-end">
              <button class="btn ghost sm" @click="print"><i class="ti ti-printer" /> طباعة</button>
              <button class="btn ghost sm" :disabled="exporting" @click="exportPdf"><i class="ti ti-file-type-pdf" /> {{ exporting ? 'جاري التصدير…' : 'تصدير PDF' }}</button>
              <template v-if="canManage">
                <button v-if="q.status === 'draft'" class="btn info sm" @click="setStatus('sent', 'تم تعليم العرض كمُرسَل')"><i class="ti ti-send" /> إرسال</button>
                <button v-if="['draft','sent'].includes(q.status)" class="btn ok sm" @click="setStatus('accepted', 'تم قبول العرض')"><i class="ti ti-check" /> قبول</button>
                <button v-if="['draft','sent'].includes(q.status)" class="btn ghost sm" @click="setStatus('rejected', 'تم رفض العرض')"><i class="ti ti-x" /> رفض</button>
                <button v-if="['draft','sent'].includes(q.status) && q.is_expired" class="btn ghost sm" @click="setStatus('expired', 'تم تعليم العرض كمنتهٍ')"><i class="ti ti-clock-off" /> منتهٍ</button>
                <button v-if="['accepted','rejected','expired'].includes(q.status)" class="btn ghost sm" @click="setStatus('draft', 'تم إعادة فتح العرض')"><i class="ti ti-refresh" /> إعادة فتح</button>
                <button v-if="q.status !== 'converted' && q.status !== 'rejected'" class="btn sm" @click="converting = !converting"><i class="ti ti-arrows-exchange" /> تحويل إلى فاتورة</button>
                <button v-if="editable" class="btn ghost sm" @click="editing = true"><i class="ti ti-pencil" /> تعديل</button>
                <button v-if="q.status !== 'converted'" class="btn ghost sm" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /> حذف</button>
              </template>
            </div>
          </div>

          <div v-if="converting" class="pay-panel mt">
            <div class="card-title" style="margin-bottom:8px"><i class="ti ti-arrows-exchange" /> تحويل العرض إلى فاتورة</div>
            <div class="row" style="align-items:flex-end;gap:10px">
              <div class="field" style="margin:0"><label>تاريخ الفاتورة</label><input v-model="conv.date" type="date" dir="ltr" /></div>
              <div class="field" style="margin:0"><label>شروط السداد (أيام)</label><input v-model="conv.payment_terms_days" type="number" min="0" max="365" dir="ltr" style="width:120px" placeholder="30" /></div>
              <div class="field" style="margin:0"><label>حالة الفاتورة</label><UiSelect v-model="conv.status" style="width:140px"><option value="sent">صادرة</option><option value="draft">مسودّة</option></UiSelect></div>
              <button class="btn" :disabled="saving" @click="convert"><i class="ti ti-check" /> إنشاء الفاتورة</button>
              <button class="btn ghost" @click="converting = false">إغلاق</button>
            </div>
            <small class="muted">سيُنقل العميل والبنود والإجماليات كما هي، ويُعلَّم العرض "محوّل لفاتورة".</small>
          </div>

          <hr style="border:none;border-top:1px solid var(--line);margin:16px 0">
          <div class="grid grid-3">
            <div>
              <small class="muted">العميل</small><br />
              <b v-if="q.customer"><NuxtLink :to="`/crm/customers/${q.customer.id}`" style="color:var(--info)">{{ q.customer.name }}</NuxtLink></b><b v-else>—</b>
              <div v-if="q.customer" class="muted" style="font-size:12px">{{ [q.customer.phone, q.customer.email].filter(Boolean).join(' · ') }}<br />{{ [q.customer.address, q.customer.city].filter(Boolean).join('، ') }}</div>
            </div>
            <div><small class="muted">مرجع العميل</small><br /><b dir="ltr">{{ q.reference || '—' }}</b><br /><small class="muted">أُرسل</small><br /><small>{{ q.sent_at ? f.dt(q.sent_at) : '—' }}</small></div>
            <div><small class="muted">قُبل</small><br /><small>{{ q.accepted_at ? f.dt(q.accepted_at) : '—' }}</small><br /><small class="muted">الشروط</small><br /><small>{{ q.terms || '—' }}</small></div>
          </div>
        </div>

        <div class="card">
          <div class="card-title"><i class="ti ti-list-details" /> البنود ({{ q.items.length }})</div>
          <table class="table">
            <thead><tr><th>#</th><th>الوصف</th><th>الكمية</th><th>الوحدة</th><th>سعر الوحدة</th><th>خصم</th><th>الإجمالي</th></tr></thead>
            <tbody><tr v-for="(l, i) in q.items" :key="l.id"><td class="muted">{{ i + 1 }}</td><td>{{ l.description }}<small v-if="l.item?.sku" class="muted" dir="ltr"> ({{ l.item.sku }})</small></td><td class="num">{{ f.qty(l.qty) }}</td><td>{{ l.unit || '—' }}</td><td class="num">{{ f.money(l.unit_price) }}</td><td class="num">{{ f.money(l.discount) }}</td><td class="num"><b>{{ f.money(l.total) }}</b></td></tr></tbody>
          </table>
          <div class="totals-box" style="margin-right:auto;max-width:340px">
            <div class="row between"><span>المجموع</span><b>{{ f.money(q.subtotal) }}</b></div>
            <div class="row between"><span>الخصم<small v-if="q.discount_type === 'percent'" class="muted"> ({{ +q.discount_value }}%)</small></span><b>- {{ f.money(q.discount) }}</b></div>
            <div class="row between"><span>الضريبة ({{ q.tax_rate }}%)</span><b>{{ f.money(q.tax) }}</b></div>
            <div class="row between total"><span>الإجمالي</span><b>{{ f.money(q.total, true) }}</b></div>
          </div>
          <p v-if="q.notes" class="mt"><small class="muted">ملاحظات للعميل</small><br />{{ q.notes }}</p>
          <p v-if="q.internal_notes" class="mt" style="background:var(--bg);padding:10px;border-radius:10px"><small class="muted"><i class="ti ti-lock" /> ملاحظات داخلية</small><br />{{ q.internal_notes }}</p>
        </div>
      </template>
    </div>

    <DocPrint kind="quote" :doc="q" :company="data.company" />
  </div>
  <div v-else-if="notFound" class="card" style="text-align:center;padding:50px 20px">
    <i class="ti ti-file-off" style="font-size:48px;color:var(--muted)" />
    <h2 class="mt">عرض السعر غير موجود</h2>
    <p class="muted">ربما حُذف أو أن الرابط غير صحيح.</p>
    <NuxtLink to="/crm/quotes" class="btn ghost mt">العودة إلى عروض الأسعار</NuxtLink>
  </div>
</template>

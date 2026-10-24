<script setup lang="ts">
definePageMeta({ title: 'ملف العقد', titleEn: 'Contract' })
const api = useApi(); const auth = useAuth(); const f = useFmt(); const route = useRoute(); const terms = useRentalTerms()
const id = Number(route.params.id)
const c = ref<any>(null); const err = ref(''); const msg = ref(''); const busy = ref(false)
const editing = ref(false); const form = reactive<any>(blankContract()); const assets = ref<any[]>([]); const customers = ref<any[]>([])
const pay = reactive({ amount: 0, method: 'cash', date: f.today(), installment_id: '' as any, reference: '', note: '' })
const dep = reactive({ amount: 0, method: 'cash', date: f.today(), note: '' })
const canManage = computed(() => auth.can('rental.manage'))

async function load() {
  c.value = await api.get(`/app/rental/contracts/${id}`)
  const open = c.value.installments.find((i: any) => ['due', 'partial'].includes(i.status))
  pay.installment_id = open?.id || ''; pay.amount = open ? +(+open.amount - +open.paid).toFixed(3) : 0
  dep.amount = c.value.deposit_status === 'held' ? +c.value.deposit_paid : Math.max(0, +c.value.deposit - +c.value.deposit_paid)
}
onMounted(load)
async function act(fn: () => Promise<any>, ok: string) { err.value = ''; msg.value = ''; busy.value = true; try { c.value = await fn(); msg.value = ok; await load() } catch (e: any) { err.value = e.message; window.scrollTo({ top: 0, behavior: 'smooth' }) } finally { busy.value = false } }
const collect = () => act(() => api.post(`/app/rental/contracts/${id}/collect`, { ...pay, installment_id: pay.installment_id || null }), tt('تم تسجيل الدفعة', 'Payment recorded'))
const deposit = (kind: string) => act(() => api.post(`/app/rental/contracts/${id}/deposit`, { ...dep, kind }), kind === 'deposit' ? tt('تم استلام التأمين', 'Deposit received') : tt('تم إرجاع التأمين', 'Deposit returned'))
const closeAs = async (status: 'ended' | 'terminated') => { if (await uiConfirm(status === 'ended' ? tt('إنهاء العقد؟ ستُلغى الدفعات المستقبلية غير المدفوعة.', 'End the contract? Future unpaid payments are cancelled.') : tt('فسخ العقد اليوم؟ ستُلغى الدفعات المستقبلية غير المدفوعة.', 'Terminate today? Future unpaid payments are cancelled.'))) act(() => api.post(`/app/rental/contracts/${id}/close`, { status }), tt('تم تحديث حالة العقد', 'Contract updated')) }
function payInstallment(i: any) { pay.installment_id = i.id; pay.amount = +(+i.amount - +i.paid).toFixed(3); document.getElementById('collect')?.scrollIntoView({ behavior: 'smooth', block: 'center' }) }

async function startEdit() {
  Object.assign(form, contractToForm(c.value))
  assets.value = (await api.get('/app/rental/assets', { leasable: 1 })).data
  customers.value = await api.get('/app/customers', { all: 1, active_only: 1 })
  editing.value = true
}
async function saveEdit() { err.value = ''; try { await api.patch(`/app/rental/contracts/${id}`, contractPayload(form)); editing.value = false; msg.value = tt('تم حفظ التعديلات', 'Changes saved'); await load() } catch (e: any) { err.value = e.message } }
async function remove() { if (!await uiConfirm(tt('حذف العقد؟', 'Delete contract?'))) return; try { await api.del(`/app/rental/contracts/${id}`); navigateTo('/rental/contracts') } catch (e: any) { uiAlert(e.message) } }

const balance = computed(() => c.value ? +(+c.value.total - +c.value.paid).toFixed(3) : 0)
const today = f.today()
const isLate = (i: any) => ['due', 'partial'].includes(i.status) && i.due_date < today
</script>

<template>
  <div v-if="c">
    <div class="breadcrumb"><NuxtLink to="/rental/contracts">{{ tt('العقود', 'Contracts') }}</NuxtLink> › <b dir="ltr">{{ c.number }}</b></div>
    <div v-if="msg" class="alert ok">{{ msg }}</div>
    <div v-if="err" class="alert bad">{{ err }}</div>

    <div v-if="editing" class="card">
      <RentalContractForm :form="form" :assets="assets" :customers="customers" editing />
      <div class="row" style="gap:10px;margin-top:18px;border-top:1px solid var(--line);padding-top:16px">
        <button class="btn" @click="saveEdit"><i class="ti ti-check" /> {{ tt('حفظ التعديلات', 'Save changes') }}</button>
        <button class="btn ghost" @click="editing = false">{{ tt('إلغاء', 'Cancel') }}</button>
      </div>
    </div>

    <template v-else>
      <div class="card">
        <div class="row between">
          <div>
            <div class="row" style="gap:10px;align-items:center"><h2 style="margin:0" dir="ltr">{{ c.number }}</h2><span class="badge" :class="contractStatus[c.status]?.cls">{{ contractStatus[c.status]?.label }}</span><span v-if="c.kind === 'installment'" class="badge info">{{ tt('بيع بالتقسيط', 'Installment sale') }}</span></div>
            <p class="muted" style="margin:6px 0 0">
              {{ terms.renter }}: <b>{{ c.customer?.name }}</b> <span dir="ltr">{{ c.customer?.phone }}</span> ·
              {{ terms.asset }}: <NuxtLink :to="`/rental/assets/${c.asset_id}`" style="color:var(--info)">{{ c.asset?.parent ? c.asset.parent.name + ' — ' : '' }}{{ c.asset?.name }}</NuxtLink>
              <template v-if="c.asset?.owner"> · {{ tt('المالك', 'Owner') }}: {{ c.asset.owner.name }}</template>
            </p>
            <p class="muted" style="margin:4px 0 0"><span dir="ltr">{{ c.start_date }} → {{ c.end_date }}</span> · {{ f.money(c.rent_amount) }} {{ frequencies[c.frequency] }}<template v-if="c.terminated_at"> · {{ tt('أُغلق في', 'Closed on') }} <span dir="ltr">{{ c.terminated_at }}</span></template></p>
          </div>
          <div v-if="canManage" class="row" style="gap:8px">
            <template v-if="c.status === 'active'">
              <button class="btn ghost sm" @click="startEdit"><i class="ti ti-pencil" /> {{ tt('تعديل', 'Edit') }}</button>
              <button class="btn ghost sm" @click="closeAs('ended')"><i class="ti ti-flag" /> {{ tt('إنهاء', 'End') }}</button>
              <button class="btn ghost sm" style="color:var(--primary)" @click="closeAs('terminated')"><i class="ti ti-ban" /> {{ tt('فسخ', 'Terminate') }}</button>
            </template>
            <button v-if="!c.payments.length" class="btn ghost sm icon" style="color:var(--primary)" @click="remove"><i class="ti ti-trash" /></button>
          </div>
        </div>
      </div>

      <div class="grid grid-4 mt">
        <div class="card stat"><div class="ic" style="background:var(--info)"><i class="ti ti-file-dollar" /></div><div><b>{{ f.money(c.total) }}</b><small>{{ tt('قيمة العقد', 'Contract value') }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--ok)"><i class="ti ti-cash" /></div><div><b>{{ f.money(c.paid) }}</b><small>{{ tt('المحصّل', 'Collected') }} · {{ tt('متبقٍ', 'left') }} {{ f.money(balance) }}</small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--primary)"><i class="ti ti-alert-triangle" /></div><div><b>{{ f.money(c.overdue) }}</b><small>{{ tt('متأخر', 'Overdue') }}<template v-if="c.next_due"> · {{ tt('القادم', 'next') }} <span dir="ltr">{{ c.next_due.due_date }}</span></template></small></div></div>
        <div class="card stat"><div class="ic" style="background:var(--warn)"><i class="ti ti-shield-lock" /></div><div><b>{{ f.money(c.deposit_paid) }}</b><small>{{ tt('التأمين', 'Deposit') }}: {{ depositStatus[c.deposit_status] }} ({{ tt('المطلوب', 'required') }} {{ f.money(c.deposit) }})</small></div></div>
      </div>

      <div class="grid grid-2 mt" style="align-items:start">
        <div class="card">
          <div class="card-title"><i class="ti ti-list-numbers" /> {{ tt('جدول الدفعات', 'Payment schedule') }}</div>
          <table class="table">
            <thead><tr><th>#</th><th>{{ tt('الاستحقاق', 'Due') }}</th><th>{{ tt('المبلغ', 'Amount') }}</th><th>{{ tt('المدفوع', 'Paid') }}</th><th>{{ tt('الحالة', 'Status') }}</th><th></th></tr></thead>
            <tbody>
              <tr v-for="i in c.installments" :key="i.id" :style="isLate(i) ? 'background:var(--primary-50)' : ''">
                <td class="muted">{{ i.seq }}</td>
                <td dir="ltr" style="text-align:right">{{ i.due_date }}<br /><small v-if="isLate(i)" style="color:var(--primary)">{{ tt(`متأخر ${daysLate(i.due_date)} يوم`, `${daysLate(i.due_date)}d late`) }}</small></td>
                <td class="num">{{ f.money(i.amount) }}</td><td class="num">{{ f.money(i.paid) }}</td>
                <td><span class="badge" :class="installmentStatus[i.status]?.cls">{{ installmentStatus[i.status]?.label }}</span></td>
                <td><button v-if="c.status === 'active' && ['due', 'partial'].includes(i.status)" class="btn ghost sm" @click="payInstallment(i)">{{ tt('تحصيل', 'Collect') }}</button></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div>
          <div v-if="c.status === 'active' && balance > 0" id="collect" class="card">
            <div class="card-title"><i class="ti ti-cash" /> {{ tt('تحصيل دفعة', 'Collect payment') }}</div>
            <div class="grid grid-2">
              <div class="field"><label>{{ tt('المبلغ', 'Amount') }}</label><input v-model.number="pay.amount" type="number" min="0" step="0.001" dir="ltr" /><small class="muted">{{ tt('الزائد يُوزَّع على الدفعات التالية تلقائياً', 'Any excess is applied to the next payments') }}</small></div>
              <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="pay.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option></UiSelect></div>
              <div class="field"><label>{{ tt('التاريخ', 'Date') }}</label><input v-model="pay.date" type="date" dir="ltr" /></div>
              <div class="field"><label>{{ tt('المرجع (رقم الإيصال / الحوالة)', 'Reference') }}</label><input v-model="pay.reference" dir="ltr" /></div>
            </div>
            <button class="btn ok" :disabled="busy || !(pay.amount > 0)" @click="collect"><i class="ti ti-check" /> {{ tt('تسجيل الدفعة', 'Record payment') }}</button>
          </div>

          <div v-if="+c.deposit > 0 || +c.deposit_paid > 0" class="card">
            <div class="card-title"><i class="ti ti-shield-lock" /> {{ tt('التأمين', 'Security deposit') }}</div>
            <div class="grid grid-3">
              <div class="field"><label>{{ tt('المبلغ', 'Amount') }}</label><input v-model.number="dep.amount" type="number" min="0" step="0.001" dir="ltr" /></div>
              <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="dep.method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option></UiSelect></div>
              <div class="field"><label>{{ tt('التاريخ', 'Date') }}</label><input v-model="dep.date" type="date" dir="ltr" /></div>
            </div>
            <div class="row">
              <button v-if="+c.deposit_paid < +c.deposit" class="btn" :disabled="busy" @click="deposit('deposit')"><i class="ti ti-download" /> {{ tt('استلام التأمين', 'Receive deposit') }}</button>
              <button v-if="+c.deposit_paid > 0" class="btn ghost" :disabled="busy" @click="deposit('deposit_return')"><i class="ti ti-upload" /> {{ tt('إرجاع التأمين', 'Return deposit') }}</button>
            </div>
          </div>

          <div class="card">
            <div class="card-title"><i class="ti ti-history" /> {{ tt('سجل الحركات المالية', 'Payment history') }}</div>
            <table v-if="c.payments.length" class="table">
              <tbody><tr v-for="p in c.payments" :key="p.id"><td dir="ltr" style="text-align:right">{{ p.date }}</td><td>{{ paymentKind[p.kind] }}</td><td>{{ f.methodLabel(p.method) }}</td><td class="num" :style="p.kind === 'deposit_return' ? 'color:var(--primary)' : ''">{{ p.kind === 'deposit_return' ? '-' : '' }}{{ f.money(p.amount) }}</td><td class="muted">{{ p.reference || p.user?.name }}</td></tr></tbody>
            </table>
            <p v-else class="empty">{{ tt('لا توجد حركات بعد.', 'No payments yet.') }}</p>
          </div>

          <div v-if="c.terms || c.notes" class="card">
            <div class="card-title"><i class="ti ti-file-text" /> {{ tt('الشروط والملاحظات', 'Terms & notes') }}</div>
            <p v-if="c.terms" style="white-space:pre-line;margin-top:0">{{ c.terms }}</p>
            <p v-if="c.notes" class="muted" style="white-space:pre-line">{{ c.notes }}</p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
